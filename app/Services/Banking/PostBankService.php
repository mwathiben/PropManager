<?php

declare(strict_types=1);

namespace App\Services\Banking;

use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase-30 INT-BANK-PARITY-1: Kenya Post Bank inbound webhook + statement
 * retrieval. Same shape as Equity/KCB/Coop:
 *   - validateWebhook: HMAC-SHA256 against payload, with per-landlord
 *     secret override (CRYPTO-11 pattern).
 *   - parsePaymentNotification: normalize the Post Bank "credit alert"
 *     JSON into the bank-agnostic PaymentNotification value object.
 *   - getTransactionHistory + verifyAccountNumber: read-only API used
 *     by the nightly bank-reconciliation:audit command.
 *
 * Endpoints (sandbox + production) come from config/services.php.
 */
class PostBankService implements BankServiceInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.postbank.sandbox', true)
            ? (string) config('services.postbank.sandbox_url', 'https://sandbox.postbank.co.ke')
            : (string) config('services.postbank.url', 'https://api.postbank.co.ke');
    }

    public function validateWebhook(string $signature, string $payload, ?string $overrideSecret = null): bool
    {
        $secret = $overrideSecret ?? (string) config('services.postbank.webhook_secret', '');
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parsePaymentNotification(array $payload): PaymentNotification
    {
        $amount = WebhookAmountParser::parse(
            $payload['amount'] ?? $payload['transactionAmount'] ?? null,
        );

        return new PaymentNotification(
            bankCode: 'postbank',
            transactionId: (string) ($payload['transactionId'] ?? $payload['referenceNumber']),
            amount: $amount->toFloatLossy(),
            accountNumber: (string) ($payload['accountNumber'] ?? $payload['creditAccount']),
            reference: $payload['narration'] ?? $payload['reference'] ?? null,
            senderName: $payload['payerName'] ?? $payload['senderName'] ?? null,
            senderPhone: $payload['payerMobile'] ?? $payload['senderMobile'] ?? null,
            transactionDate: new DateTime($payload['transactionDate'] ?? $payload['valueDate'] ?? 'now'),
            rawPayload: $payload,
        );
    }

    public function getTransactionHistory(string $accountNumber, DateTime $from, DateTime $to): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return [];
        }
        try {
            $response = Http::withToken($token)
                ->connectTimeout(3)->timeout(15)
                ->retry(2, 200, throw: false)
                ->get("{$this->baseUrl}/account/v1/{$accountNumber}/statement", [
                    'from' => $from->format('Y-m-d'),
                    'to' => $to->format('Y-m-d'),
                ]);
            if ($response->successful()) {
                return $response->json('transactions', []);
            }
            Log::error('Post Bank statement fetch failed', ['status' => $response->status()]);

            return [];
        } catch (\Throwable $e) {
            Log::error('Post Bank statement exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function verifyAccountNumber(string $accountNumber): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }
        try {
            $response = Http::withToken($token)
                ->connectTimeout(3)->timeout(15)
                ->retry(2, 200, throw: false)
                ->get("{$this->baseUrl}/account/v1/{$accountNumber}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('Post Bank account verification exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function getAccessToken(): ?string
    {
        $credentialKey = sha1((string) config('services.postbank.client_id', ''));
        $cacheKey = "postbank_access_token:{$credentialKey}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        return Cache::remember($cacheKey, 3500, function () {
            try {
                $response = Http::connectTimeout(3)->timeout(15)
                    ->retry(2, 200, throw: false)
                    ->asForm()->post("{$this->baseUrl}/oauth/token", [
                        'grant_type' => 'client_credentials',
                        'client_id' => config('services.postbank.client_id'),
                        'client_secret' => config('services.postbank.client_secret'),
                    ]);
                if ($response->successful()) {
                    return $response->json('access_token');
                }
                Log::error('Post Bank auth failed', ['status' => $response->status()]);

                return null;
            } catch (\Throwable $e) {
                Log::error('Post Bank auth exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }
}
