<?php

namespace App\Services;

use App\Models\PaymentConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected string $consumerKey;

    protected string $consumerSecret;

    protected string $baseUrl;

    protected string $environment;

    public function __construct()
    {
        $this->environment = config('mpesa.environment', 'sandbox') ?? 'sandbox';
        $this->consumerKey = config('mpesa.consumer_key') ?? '';
        $this->consumerSecret = config('mpesa.consumer_secret') ?? '';
        $this->baseUrl = config("mpesa.endpoints.{$this->environment}") ?? '';
    }

    public function getAccessToken(): ?string
    {
        $cacheKey = 'mpesa_access_token_'.$this->environment;

        return Cache::remember($cacheKey, 3500, function () {
            try {
                $credentials = base64_encode("{$this->consumerKey}:{$this->consumerSecret}");

                $response = Http::withHeaders([
                    'Authorization' => 'Basic '.$credentials,
                ])->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('M-Pesa auth failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('M-Pesa auth exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    public function initiateSTKPush(array $data, ?PaymentConfiguration $config = null): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        if ($config && $config->hasMpesaSTKConfig()) {
            $shortcode = $config->mpesa_shortcode;
            $passkey = $config->mpesa_passkey;
            $transactionType = $config->getMpesaCommandId();
            $accountRef = $config->usesTillNumber()
                ? $shortcode
                : ($data['account_reference'] ?? $this->generateAccountReference());
        } else {
            $shortcode = config('mpesa.stk.shortcode');
            $passkey = config('mpesa.stk.passkey');
            $transactionType = config('mpesa.stk.transaction_type', 'CustomerPayBillOnline');
            $accountRef = $data['account_reference'] ?? $this->generateAccountReference();
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode.$passkey.$timestamp);
        $phone = $this->formatPhoneNumber($data['phone']);

        try {
            $response = Http::withHeaders([
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
                'response' => $result,
                'status' => $response->status(),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('M-Pesa STK Push exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function querySTKStatus(string $checkoutRequestId): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        $shortcode = config('mpesa.stk.shortcode');
        $passkey = config('mpesa.stk.passkey');
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode.$passkey.$timestamp);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('M-Pesa STK query exception', ['error' => $e->getMessage()]);

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
            $response = Http::withHeaders([
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
                Log::info("M-Pesa {$type} C2B URLs registered", ['response' => $result]);

                return $result;
            }

            Log::error("M-Pesa {$type} C2B registration failed", ['response' => $result]);

            return $result;
        } catch (\Exception $e) {
            Log::error("M-Pesa {$type} C2B registration exception", ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function initiateB2C(string $phone, float $amount, string $reference, string $remarks): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        $phone = $this->formatPhoneNumber($phone);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/mpesa/b2c/v1/paymentrequest", [
                'InitiatorName' => config('mpesa.b2c.initiator_name'),
                'SecurityCredential' => config('mpesa.b2c.security_credential'),
                'CommandID' => 'BusinessPayment',
                'Amount' => (int) $amount,
                'PartyA' => config('mpesa.b2c.shortcode'),
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

            Log::error('M-Pesa B2C initiation failed', ['response' => $result]);

            return null;
        } catch (\Exception $e) {
            Log::error('M-Pesa B2C exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function queryTransactionStatus(string $transactionId): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/mpesa/transactionstatus/v1/query", [
                'Initiator' => config('mpesa.b2c.initiator_name'),
                'SecurityCredential' => config('mpesa.b2c.security_credential'),
                'CommandID' => 'TransactionStatusQuery',
                'TransactionID' => $transactionId,
                'PartyA' => config('mpesa.b2c.shortcode'),
                'IdentifierType' => '4',
                'ResultURL' => config('mpesa.b2c.result_url'),
                'QueueTimeOutURL' => config('mpesa.b2c.timeout_url'),
                'Remarks' => 'Transaction status query',
            ]);

            $result = $response->json();

            if ($response->successful()) {
                return $result;
            }

            Log::error('M-Pesa transaction status query failed', ['response' => $result]);

            return null;
        } catch (\Exception $e) {
            Log::error('M-Pesa transaction status exception', ['error' => $e->getMessage()]);

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
        return ! empty($this->consumerKey)
            && ! empty($this->consumerSecret)
            && ! empty(config('mpesa.stk.shortcode'));
    }
}
