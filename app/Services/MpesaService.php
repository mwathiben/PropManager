<?php

namespace App\Services;

use App\Models\PaymentConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected string $consumerKey = '';

    protected string $consumerSecret = '';

    protected string $baseUrl;

    protected string $environment;

    protected ?PaymentConfiguration $config = null;

    private const TIMEOUT_SECONDS = 30;

    private const RETRY_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 100;

    public function __construct(?PaymentConfiguration $config = null)
    {
        $this->environment = config('mpesa.environment', 'sandbox') ?? 'sandbox';
        $this->baseUrl = config("mpesa.endpoints.{$this->environment}") ?? '';

        if ($config !== null && $config->hasMpesaApiConfig()) {
            $this->config = $config;
            $this->consumerKey = $config->mpesa_consumer_key;
            $this->consumerSecret = $config->mpesa_consumer_secret;
        }
    }

    public function withConfig(PaymentConfiguration $config): self
    {
        if (! $config->hasMpesaApiConfig()) {
            throw new \InvalidArgumentException(
                'MpesaService requires a PaymentConfiguration with M-Pesa credentials. '
                    .'Configure in Settings > Payment Methods.'
            );
        }

        $this->config = $config;
        $this->consumerKey = $config->mpesa_consumer_key;
        $this->consumerSecret = $config->mpesa_consumer_secret;

        return $this;
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            throw new \InvalidArgumentException(
                'MpesaService requires M-Pesa credentials. Call withConfig() first or construct with PaymentConfiguration.'
            );
        }
    }

    public function getAccessToken(): ?string
    {
        $this->ensureConfigured();

        $cacheKey = 'mpesa_access_token_'.$this->environment.'_'.$this->config?->id;

        return Cache::remember($cacheKey, 3500, function () {
            try {
                $credentials = base64_encode("{$this->consumerKey}:{$this->consumerSecret}");

                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                        return $exception instanceof ConnectionException;
                    }, throw: false)
                    ->withHeaders([
                        'Authorization' => 'Basic '.$credentials,
                    ])->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('M-Pesa auth failed', [
                    'status' => $response->status(),
                    'body' => $this->redactSecrets($response->body()),
                ]);

                return null;
            } catch (ConnectionException $e) {
                Log::error('M-Pesa auth connection failed', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('M-Pesa auth exception', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    public function initiateSTKPush(array $data, PaymentConfiguration $config): ?array
    {
        if (! $config->hasMpesaSTKConfig()) {
            throw new \InvalidArgumentException(
                'STK Push requires PaymentConfiguration with M-Pesa STK settings (shortcode, passkey). '
                    .'Configure in Settings > Payment Methods.'
            );
        }

        $this->withConfig($config);
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        $shortcode = $config->mpesa_shortcode;
        $passkey = $config->mpesa_passkey;
        $transactionType = $config->getMpesaCommandId();
        $accountRef = $config->usesTillNumber()
            ? $shortcode
            : ($data['account_reference'] ?? $this->generateAccountReference());

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode.$passkey.$timestamp);
        $phone = $this->formatPhoneNumber($data['phone']);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => $transactionType,
                    'Amount' => (int) $data['amount'],
                    'PartyA' => $phone,
                    'PartyB' => $shortcode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $data['callback_url'] ?? config('mpesa.stk.callback_url'),
                    'AccountReference' => $accountRef,
                    'TransactionDesc' => $data['description'] ?? 'Payment',
                ]);

            $result = $response->json();

            if ($response->successful() && isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                Log::info('M-Pesa STK Push initiated', [
                    'checkout_request_id' => $result['CheckoutRequestID'] ?? null,
                    'phone' => substr($phone, -4),
                    'shortcode_type' => $config?->mpesa_shortcode_type ?? 'global',
                ]);

                return $result;
            }

            Log::error('M-Pesa STK Push failed', [
                'body' => $this->redactSecrets(json_encode($result)),
                'status' => $response->status(),
            ]);

            return $result;
        } catch (ConnectionException $e) {
            Log::error('M-Pesa STK Push connection failed', [
                'error' => $e->getMessage(),
                'phone' => substr($phone, -4),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('M-Pesa STK Push exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function querySTKStatus(string $checkoutRequestId, PaymentConfiguration $config): ?array
    {
        if (! $config->hasMpesaSTKConfig()) {
            throw new \InvalidArgumentException(
                'STK query requires PaymentConfiguration with M-Pesa STK settings.'
            );
        }

        $this->withConfig($config);
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        $shortcode = $config->mpesa_shortcode;
        $passkey = $config->mpesa_passkey;
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode.$passkey.$timestamp);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'CheckoutRequestID' => $checkoutRequestId,
                ]);

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('M-Pesa STK query connection failed', [
                'checkout_request_id' => $checkoutRequestId,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('M-Pesa STK query exception', [
                'checkout_request_id' => $checkoutRequestId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function registerC2BUrls(string $type = 'paybill'): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        $config = $type === 'till' ? config('mpesa.till') : config('mpesa.c2b');

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/mpesa/c2b/v1/registerurl", [
                    'ShortCode' => $config['shortcode'],
                    'ResponseType' => 'Completed',
                    'ConfirmationURL' => $config['confirmation_url'],
                    'ValidationURL' => $config['validation_url'],
                ]);

            $result = $response->json();

            if ($response->successful()) {
                Log::info("M-Pesa {$type} C2B URLs registered", [
                    'body' => $this->redactSecrets(json_encode($result)),
                ]);

                return $result;
            }

            Log::error("M-Pesa {$type} C2B registration failed", [
                'body' => $this->redactSecrets(json_encode($result)),
                'status' => $response->status(),
            ]);

            return $result;
        } catch (ConnectionException $e) {
            Log::error("M-Pesa {$type} C2B registration connection failed", [
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("M-Pesa {$type} C2B registration exception", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Initiate B2C payment (NO RETRY - financial operation)
     */
    public function initiateB2C(string $phone, float $amount, string $reference, string $remarks): ?array
    {
        $this->ensureConfigured();

        if (! $this->config || ! $this->config->hasMpesaB2CConfig()) {
            Log::error('M-Pesa B2C not configured for this landlord', [
                'landlord_id' => $this->config->landlord_id,
            ]);

            return null;
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        $phone = $this->formatPhoneNumber($phone);

        try {
            // NO RETRY for B2C - financial operation must not be duplicated
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/mpesa/b2c/v1/paymentrequest", [
                    'InitiatorName' => $this->config->mpesa_b2c_initiator,
                    'SecurityCredential' => $this->config->mpesa_b2c_security_credential,
                    'CommandID' => 'BusinessPayment',
                    'Amount' => (int) $amount,
                    'PartyA' => $this->config->mpesa_b2c_shortcode,
                    'PartyB' => $phone,
                    'Remarks' => $remarks,
                    'QueueTimeOutURL' => config('mpesa.b2c.timeout_url'),
                    'ResultURL' => config('mpesa.b2c.result_url'),
                    'Occasion' => $reference,
                ]);

            $result = $response->json();

            if ($response->successful() && ($result['ResponseCode'] ?? '') === '0') {
                Log::info('M-Pesa B2C initiated', [
                    'conversation_id' => $result['ConversationID'] ?? null,
                    'phone' => substr($phone, -4),
                    'amount' => $amount,
                ]);

                return [
                    'conversation_id' => $result['ConversationID'],
                    'originator_conversation_id' => $result['OriginatorConversationID'],
                ];
            }

            Log::error('M-Pesa B2C initiation failed', [
                'body' => $this->redactSecrets(json_encode($result)),
                'status' => $response->status(),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('M-Pesa B2C connection failed', [
                'phone' => substr($phone, -4),
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('M-Pesa B2C exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function queryTransactionStatus(string $transactionId): ?array
    {
        $this->ensureConfigured();

        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/mpesa/transactionstatus/v1/query", [
                    'Initiator' => $this->config->mpesa_b2c_initiator,
                    'SecurityCredential' => $this->config->mpesa_b2c_security_credential,
                    'CommandID' => 'TransactionStatusQuery',
                    'TransactionID' => $transactionId,
                    'PartyA' => $this->config->mpesa_b2c_shortcode,
                    'IdentifierType' => '4',
                    'ResultURL' => config('mpesa.b2c.result_url'),
                    'QueueTimeOutURL' => config('mpesa.b2c.timeout_url'),
                    'Remarks' => 'Transaction status query',
                ]);

            $result = $response->json();

            if ($response->successful()) {
                return $result;
            }

            Log::error('M-Pesa transaction status query failed', [
                'transaction_id' => $transactionId,
                'body' => $this->redactSecrets(json_encode($result)),
                'status' => $response->status(),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('M-Pesa transaction status connection failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('M-Pesa transaction status exception', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function validateWebhookIP(string $ip): bool
    {
        $allowedIps = config('mpesa.allowed_ips', []);

        if (empty($allowedIps)) {
            if ($this->environment === 'production') {
                Log::warning('M-Pesa webhook IP validation failed: no whitelist configured', [
                    'ip' => $ip,
                ]);

                return false;
            }

            return true;
        }

        return in_array($ip, $allowedIps);
    }

    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        } elseif (! str_starts_with($phone, '254')) {
            $phone = '254'.$phone;
        }

        return $phone;
    }

    public function generateAccountReference(?string $prefix = null): string
    {
        $prefix = $prefix ?? config('mpesa.defaults.account_reference_prefix', 'PROP');

        return strtoupper($prefix).'-'.time().'-'.substr(uniqid(), -4);
    }

    public static function generateCheckoutReference(): string
    {
        return 'MPESA-'.time().'-'.strtoupper(substr(uniqid(), -6));
    }

    public function isConfigured(): bool
    {
        return ! empty($this->consumerKey) && ! empty($this->consumerSecret);
    }

    /**
     * Redact sensitive data from response body before logging
     */
    private function redactSecrets(string $body): string
    {
        $truncated = substr($body, 0, 500);

        $patterns = [
            '/("?(?:SecurityCredential|AccessToken|access_token|password|token|secret|passkey)"?\s*[:=]\s*)"[^"]*"/i' => '$1"[REDACTED]"',
            '/(Bearer\s+)[A-Za-z0-9._-]+/i' => '$1[REDACTED]',
            '/(Basic\s+)[A-Za-z0-9._=+-]+/i' => '$1[REDACTED]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $truncated) ?? $truncated;
    }
}
