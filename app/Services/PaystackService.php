<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\PaymentConfiguration;
use App\Traits\LogsExternalRequests;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    use LogsExternalRequests;

    protected $secretKey = '';

    protected $publicKey = '';

    protected $baseUrl;

    public function __construct(?PaymentConfiguration $config = null)
    {
        $this->baseUrl = 'https://api.paystack.co';

        if ($config !== null && $config->hasPaystackConfig()) {
            $this->secretKey = $config->paystack_secret_key;
            $this->publicKey = $config->paystack_public_key;
        }
    }

    public function withConfig(PaymentConfiguration $config): self
    {
        if (! $config->hasPaystackConfig()) {
            throw new \InvalidArgumentException(
                'PaystackService requires a PaymentConfiguration with Paystack credentials. '
                    .'Configure in Settings > Payment Methods.'
            );
        }

        $this->secretKey = $config->paystack_secret_key;
        $this->publicKey = $config->paystack_public_key;

        return $this;
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->secretKey) || empty($this->publicKey)) {
            throw new \InvalidArgumentException(
                'PaystackService requires Paystack credentials. Call withConfig() first or construct with PaymentConfiguration.'
            );
        }
    }

    public function isConfigured(): bool
    {
        return ! empty($this->secretKey) && ! empty($this->publicKey);
    }

    /**
     * Initialize a payment transaction
     *
     * @param  array  $data  ['email', 'amount', 'reference', 'callback_url']
     * @return array|null
     */
    public function initializeTransaction(array $data)
    {
        $this->ensureConfigured();

        try {
            $currency = Currency::tryFrom($data['currency'] ?? '') ?? Currency::default();

            $response = $this->timedHttpRequest('paystack', '/transaction/initialize', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl.'/transaction/initialize', array_filter([
                    'email' => $data['email'],
                    'amount' => $currency->toMinorUnits($data['amount']),
                    'currency' => $currency->value,
                    'reference' => $data['reference'],
                    'callback_url' => $data['callback_url'] ?? route('payments.callback'),
                    'metadata' => $data['metadata'] ?? [],
                ])));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack initialization failed', [
                'status' => $response->status(),
                'body' => $this->redactSecrets($response->body()),
                'reference' => $data['reference'] ?? null,
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack initialization connection failed', [
                'error' => $e->getMessage(),
                'reference' => $data['reference'] ?? null,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack initialization exception', [
                'error' => $e->getMessage(),
                'reference' => $data['reference'] ?? null,
            ]);

            return null;
        }
    }

    /**
     * Verify a payment transaction
     *
     * @return array|null
     */
    public function verifyTransaction(string $reference)
    {
        $this->ensureConfigured();

        try {
            $response = $this->timedHttpRequest('paystack', '/transaction/verify', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                ])->get($this->baseUrl.'/transaction/verify/'.$reference));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack verification failed', [
                'reference' => $reference,
                'status' => $response->status(),
                'body' => $this->redactSecrets($response->body()),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack verification connection failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack verification exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get public key for frontend initialization
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Generate a unique transaction reference
     *
     * @return string
     */
    public static function generateReference(string $prefix = 'PAY')
    {
        return $prefix.'-'.time().'-'.strtoupper(substr(uniqid(), -6));
    }

    /**
     * Create a Paystack subaccount for a landlord
     *
     * @param  array  $data  ['business_name', 'bank_code', 'account_number', 'percentage_charge', 'email', 'phone', 'metadata']
     */
    public function createSubaccount(array $data): ?array
    {
        $this->ensureConfigured();

        try {
            $response = $this->timedHttpRequest('paystack', '/subaccount', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl.'/subaccount', [
                    'business_name' => $data['business_name'],
                    'bank_code' => $data['bank_code'],
                    'account_number' => $data['account_number'],
                    'percentage_charge' => $data['percentage_charge'],
                    'primary_contact_email' => $data['email'] ?? null,
                    'primary_contact_phone' => $data['phone'] ?? null,
                    'metadata' => $data['metadata'] ?? [],
                ]));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack subaccount creation failed', [
                'status' => $response->status(),
                'body' => $this->redactSecrets($response->body()),
                'business_name' => $data['business_name'] ?? null,
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack subaccount creation connection failed', [
                'error' => $e->getMessage(),
                'business_name' => $data['business_name'] ?? null,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack subaccount creation exception', [
                'error' => $e->getMessage(),
                'business_name' => $data['business_name'] ?? null,
            ]);

            return null;
        }
    }

    /**
     * Update a Paystack subaccount
     */
    public function updateSubaccount(string $subaccountCode, array $data): ?array
    {
        $this->ensureConfigured();

        try {
            $response = $this->timedHttpRequest('paystack', '/subaccount', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/json',
                ])->put($this->baseUrl.'/subaccount/'.$subaccountCode, $data));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack subaccount update failed', [
                'subaccount_code' => $subaccountCode,
                'status' => $response->status(),
                'body' => $this->redactSecrets($response->body()),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack subaccount update connection failed', [
                'subaccount_code' => $subaccountCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack subaccount update exception', [
                'subaccount_code' => $subaccountCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch Paystack subaccount details
     */
    public function getSubaccount(string $subaccountCode): ?array
    {
        $this->ensureConfigured();

        try {
            $response = $this->timedHttpRequest('paystack', '/subaccount', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                ])->get($this->baseUrl.'/subaccount/'.$subaccountCode));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack subaccount fetch failed', [
                'subaccount_code' => $subaccountCode,
                'status' => $response->status(),
                'body' => $this->redactSecrets($response->body()),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack subaccount fetch connection failed', [
                'subaccount_code' => $subaccountCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack subaccount fetch exception', [
                'subaccount_code' => $subaccountCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * List available banks for subaccount setup
     */
    public function listBanks(string $country = 'kenya'): ?array
    {
        try {
            $response = $this->timedHttpRequest('paystack', '/bank', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                ])->get($this->baseUrl.'/bank', [
                    'country' => $country,
                ]));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack list banks failed', [
                'country' => $country,
                'status' => $response->status(),
                'body' => $this->redactSecrets($response->body()),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack list banks connection failed', [
                'country' => $country,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack list banks exception', [
                'country' => $country,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve/verify bank account number
     */
    public function resolveAccountNumber(string $accountNumber, string $bankCode): ?array
    {
        try {
            $response = $this->timedHttpRequest('paystack', '/bank/resolve', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                ])->get($this->baseUrl.'/bank/resolve', [
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                ]));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack account resolution failed', [
                'account_number' => substr($accountNumber, -4),
                'bank_code' => $bankCode,
                'status' => $response->status(),
                'body' => $this->redactSecrets($response->body()),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack account resolution connection failed', [
                'account_number' => substr($accountNumber, -4),
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack account resolution exception', [
                'account_number' => substr($accountNumber, -4),
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify webhook signature from Paystack
     *
     * @param  string  $payload  Raw request body
     * @param  string  $signature  x-paystack-signature header value
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $computed = hash_hmac('sha512', $payload, $this->secretKey);

        return hash_equals($computed, $signature);
    }

    /**
     * Get the secret key for internal use
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * Initialize split payment transaction with subaccount
     *
     * @param  array  $data  ['email', 'amount', 'reference', 'callback_url', 'metadata', 'subaccount_code', 'bearer']
     */
    public function initializeSplitTransaction(array $data): ?array
    {
        try {
            $currency = Currency::tryFrom($data['currency'] ?? '') ?? Currency::default();
            $payload = [
                'email' => $data['email'],
                'amount' => $currency->toMinorUnits($data['amount']),
                'currency' => $currency->value,
                'reference' => $data['reference'],
                'callback_url' => $data['callback_url'] ?? route('payments.callback'),
                'metadata' => $data['metadata'] ?? [],
            ];

            // Add subaccount for split payment
            if (! empty($data['subaccount_code'])) {
                $payload['subaccount'] = $data['subaccount_code'];
                // 'account' = platform pays fees, 'subaccount' = landlord pays fees
                $payload['bearer'] = $data['bearer'] ?? 'subaccount';
            }

            // If using split code (multiple recipients)
            if (! empty($data['split_code'])) {
                unset($payload['subaccount']);
                $payload['split_code'] = $data['split_code'];
            }

            $response = $this->timedHttpRequest('paystack', '/transaction/initialize', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl.'/transaction/initialize', $payload));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack split transaction init failed', [
                'status' => $response->status(),
                'body' => $this->redactSecrets($response->body()),
                'reference' => $data['reference'] ?? null,
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack split transaction connection failed', [
                'error' => $e->getMessage(),
                'reference' => $data['reference'] ?? null,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack split transaction exception', [
                'error' => $e->getMessage(),
                'reference' => $data['reference'] ?? null,
            ]);

            return null;
        }
    }

    /**
     * Refund a transaction (NO RETRY - financial operation)
     */
    public function refundTransaction(string $reference, ?float $amount = null, string $currency = 'KES'): ?array
    {
        try {
            $data = ['transaction' => $reference];

            if ($amount !== null) {
                $data['amount'] = Currency::from($currency)->toMinorUnits($amount);
            }

            // NO RETRY for refunds - financial operation must not be duplicated
            $response = $this->timedHttpRequest('paystack', '/refund', fn () => Http::timeout($this->timeoutSeconds())
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl.'/refund', $data));

            if ($response->successful() && $response->json('status')) {
                return $response->json('data');
            }

            Log::error('Paystack refund failed', [
                'reference' => $reference,
                'status' => $response->status(),
                'body' => $this->redactSecrets(json_encode($response->json())),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack refund connection failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack refund exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getRefund(string $refundId): ?array
    {
        $this->ensureConfigured();

        try {
            $response = $this->timedHttpRequest('paystack', '/refund', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                ])->get($this->baseUrl.'/refund/'.$refundId));

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::warning('Paystack get refund failed', [
                'refund_id' => $refundId,
                'status' => $response->status(),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack get refund connection failed', [
                'refund_id' => $refundId,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack get refund exception', [
                'refund_id' => $refundId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function listRefunds(?string $reference = null): ?array
    {
        try {
            $params = [];
            if ($reference) {
                $params['reference'] = $reference;
            }

            $response = $this->timedHttpRequest('paystack', '/refund', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                ])->get($this->baseUrl.'/refund', $params));

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::warning('Paystack list refunds failed', [
                'reference' => $reference,
                'status' => $response->status(),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack list refunds connection failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack list refunds exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function timeoutSeconds(): int
    {
        return (int) config('payments.gateways.paystack.timeout_seconds', 30);
    }

    private function retryAttempts(): int
    {
        return (int) config('payments.gateways.paystack.retry_attempts', 3);
    }

    private function retryDelayMs(): int
    {
        return (int) config('payments.gateways.paystack.retry_delay_ms', 100);
    }

    private function redactSecrets(string $body): string
    {
        $truncated = substr($body, 0, 500);

        $patterns = [
            '/("?(?:secret_key|authorization|Bearer|password|token|api_key|access_token)"?\s*[:=]\s*)"[^"]*"/i' => '$1"[REDACTED]"',
            '/(Bearer\s+)[A-Za-z0-9._-]+/i' => '$1[REDACTED]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $truncated) ?? $truncated;
    }
}
