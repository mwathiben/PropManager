<?php

namespace App\Services;

use App\Exceptions\Integration\PaymentGatewayUnreachableException;
use App\Models\PaymentConfiguration;
use App\Traits\LogsExternalRequests;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntaSendService
{
    use LogsExternalRequests;

    protected PaymentConfiguration $config;

    protected string $baseUrl;

    protected string $secretKey;

    protected string $publishableKey;

    protected string $environment;

    public function __construct(PaymentConfiguration $config)
    {
        $this->config = $config;
        $this->baseUrl = $config->getIntaSendBaseUrl();
        $this->secretKey = $config->intasend_secret_key ?? '';
        $this->publishableKey = $config->intasend_publishable_key ?? '';
        $this->environment = $config->intasend_environment ?? 'sandbox';
    }

    public function isConfigured(): bool
    {
        return $this->config->hasIntaSendConfig();
    }

    /**
     * Format phone number to IntaSend expected format (254XXXXXXXXX)
     */
    public function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            if (strlen($phone) === 9) {
                $phone = '254'.$phone;
            }
        }

        return $phone;
    }

    /**
     * Generate unique reference for IntaSend transactions
     */
    public static function generateReference(string $prefix = 'ITS'): string
    {
        // CONC-8: random_bytes is unpredictable and collision-safe.
        return $prefix.'-'.time().'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Initiate M-Pesa STK Push via IntaSend
     *
     * NO RETRY - Financial operation must not be duplicated
     *
     * @param  float  $amount  Amount in KES
     * @param  string  $phone  Kenya phone number (any format)
     * @param  string  $reference  Internal reference for tracking
     * @param  array|null  $splitConfig  Split payment config ['wallet_id' => ...]
     * @return array|null Response array or null on failure
     */
    public function initializeMpesaStkPush(
        float $amount,
        string $phone,
        string $reference,
        ?array $splitConfig = null
    ): ?array {
        if (! $this->isConfigured()) {
            Log::error('IntaSend STK Push failed: not configured');

            return null;
        }

        $phone = $this->formatPhoneNumber($phone);
        $payload = $this->buildStkPushPayload($amount, $phone, $reference, $splitConfig);

        try {
            /** @var Response $response */
            $response = $this->timedHttpRequest('intasend', '/api/v1/payment/mpesa-stk-push', fn () => Http::timeout($this->timeoutSeconds())
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl.'/api/v1/payment/mpesa-stk-push/', $payload));

            return $this->handleStkPushResponse($response, $phone, $reference);
        } catch (ConnectionException $e) {
            // HANDLE-1: surface gateway unreachability so callers can show a
            // 'try again later' message instead of a generic 500.
            Log::error('IntaSend STK Push connection failed', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);

            throw new PaymentGatewayUnreachableException('IntaSend', '/api/v1/payment/mpesa-stk-push', $e);
        } catch (\Exception $e) {
            Log::error('IntaSend STK Push exception', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return null;
        }
    }

    /**
     * Build the STK Push request payload, including optional split config.
     */
    private function buildStkPushPayload(float $amount, string $phone, string $reference, ?array $splitConfig): array
    {
        // IntaSend expects amount in whole currency units (e.g., 100 for KES 100)
        // Using round() to handle decimal amounts properly (99.50 → 100)
        // instead of (int) which truncates (99.50 → 99)
        $payload = [
            'amount' => (int) round($amount),
            'phone_number' => $phone,
            'api_ref' => $reference,
        ];

        if ($splitConfig !== null && isset($splitConfig['wallet_id'])) {
            $payload['wallet_id'] = $splitConfig['wallet_id'];
        }

        return $payload;
    }

    /**
     * Log and return the result of an STK Push HTTP response.
     */
    private function handleStkPushResponse(Response $response, string $phone, string $reference): ?array
    {
        $result = $response->json();

        if ($response->successful() && isset($result['invoice'])) {
            Log::info('IntaSend STK Push initiated', [
                'invoice_id' => $result['invoice']['invoice_id'] ?? null,
                'state' => $result['invoice']['state'] ?? null,
                'phone' => substr($phone, -4),
                'reference' => $reference,
            ]);

            return $result;
        }

        Log::error('IntaSend STK Push failed', [
            'status' => $response->status(),
            'body' => $this->redactSecrets(json_encode($result)),
            'reference' => $reference,
        ]);

        return null;
    }

    /**
     * Verify transaction status by invoice ID
     *
     * @param  string  $invoiceId  IntaSend invoice ID
     * @return array|null Transaction details or null on failure
     */
    public function verifyTransaction(string $invoiceId): ?array
    {
        if (! $this->isConfigured()) {
            Log::error('IntaSend verification failed: not configured');

            return null;
        }

        try {
            /** @var Response $response */
            $response = $this->timedHttpRequest('intasend', '/api/v1/payment/status', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.intasend.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl.'/api/v1/payment/status/', [
                    'invoice_id' => $invoiceId,
                ]));

            $result = $response->json();

            if ($response->successful() && isset($result['invoice'])) {
                Log::info('IntaSend transaction status retrieved', [
                    'invoice_id' => $invoiceId,
                    'state' => $result['invoice']['state'] ?? null,
                ]);

                return $result;
            }

            Log::error('IntaSend verification failed', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'body' => $this->redactSecrets(json_encode($result)),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('IntaSend verification connection failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('IntaSend verification exception', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate IntaSend webhook using challenge-based verification
     *
     * IntaSend does NOT use HMAC signatures. Each webhook payload
     * contains a 'challenge' field that must match the configured challenge.
     */
    public function validateWebhookChallenge(string $receivedChallenge): bool
    {
        $expectedChallenge = $this->config->intasend_webhook_challenge;

        if (empty($expectedChallenge)) {
            Log::warning('IntaSend webhook validation failed: no challenge configured', [
                'landlord_id' => $this->config->landlord_id ?? null,
            ]);

            return false;
        }

        $isValid = hash_equals($expectedChallenge, $receivedChallenge);

        if (! $isValid) {
            Log::warning('IntaSend webhook challenge mismatch', [
                'landlord_id' => $this->config->landlord_id ?? null,
            ]);
        }

        return $isValid;
    }

    /**
     * Get the publishable key for frontend usage
     */
    public function getPublicKey(): string
    {
        return $this->publishableKey;
    }

    /**
     * Check if transaction state indicates completion
     */
    public static function isComplete(string $state): bool
    {
        return strtoupper($state) === config('intasend.states.complete', 'COMPLETE');
    }

    /**
     * Check if transaction state indicates pending/processing
     */
    public static function isPending(string $state): bool
    {
        $state = strtoupper($state);

        return in_array($state, [
            config('intasend.states.pending', 'PENDING'),
            config('intasend.states.processing', 'PROCESSING'),
        ]);
    }

    /**
     * Check if transaction state indicates failure
     */
    public static function isFailed(string $state): bool
    {
        return strtoupper($state) === config('intasend.states.failed', 'FAILED');
    }

    private function timeoutSeconds(): int
    {
        return (int) config('payments.gateways.intasend.timeout_seconds', 30);
    }

    private function retryAttempts(): int
    {
        return (int) config('payments.gateways.intasend.retry_attempts', 3);
    }

    private function retryDelayMs(): int
    {
        return (int) config('payments.gateways.intasend.retry_delay_ms', 100);
    }

    private function redactSecrets(string $body): string
    {
        $truncated = substr($body, 0, 500);

        $patterns = [
            '/("?(?:secret_key|authorization|Bearer|password|token|api_key|challenge)"?\s*[:=]\s*)"[^"]*"/i' => '$1"[REDACTED]"',
            '/(Bearer\s+)[A-Za-z0-9._-]+/i' => '$1[REDACTED]',
            '/(ISSecretKey_)[A-Za-z0-9]+/i' => '$1[REDACTED]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $truncated) ?? $truncated;
    }
}
