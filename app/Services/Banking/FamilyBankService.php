<?php

declare(strict_types=1);

namespace App\Services\Banking;

use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase-30 INT-BANK-PARITY-2: Family Bank Kenya inbound webhook +
 * statement retrieval. Family Bank's published webhook contract uses
 * a Bearer-style shared token (not HMAC), so validateWebhook compares
 * the Authorization header literally against the configured secret —
 * keeping the BankServiceInterface contract uniform while honouring
 * Family Bank's auth quirk. parsePaymentNotification normalises their
 * credit-alert JSON into the shared PaymentNotification value object.
 */
class FamilyBankService implements BankServiceInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.familybank.sandbox', true)
            ? (string) config('services.familybank.sandbox_url', 'https://sandbox.familybank.co.ke')
            : (string) config('services.familybank.url', 'https://api.familybank.co.ke');
    }

    public function validateWebhook(string $signature, string $payload, ?string $overrideSecret = null): bool
    {
        unset($payload);
        $secret = $overrideSecret ?? (string) config('services.familybank.webhook_secret', '');
        if ($secret === '') {
            return false;
        }

        $presented = preg_replace('/^Bearer\s+/i', '', $signature) ?? $signature;

        return hash_equals($secret, (string) $presented);
    }

    public function parsePaymentNotification(array $payload): PaymentNotification
    {
        $amount = WebhookAmountParser::parse(
            $payload['amount'] ?? $payload['CreditAmount'] ?? null,
        );

        return new PaymentNotification(
            bankCode: 'familybank',
            transactionId: (string) ($payload['transactionId'] ?? $payload['TransactionRef']),
            amount: $amount->toFloatLossy(),
            accountNumber: (string) ($payload['accountNumber'] ?? $payload['BeneficiaryAccount']),
            reference: $payload['narration'] ?? $payload['Reference'] ?? null,
            senderName: $payload['senderName'] ?? $payload['PayerName'] ?? null,
            senderPhone: $payload['senderPhone'] ?? $payload['PayerMobile'] ?? null,
            transactionDate: new DateTime($payload['transactionDate'] ?? $payload['ValueDate'] ?? 'now'),
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
                ->get("{$this->baseUrl}/api/v1/account/{$accountNumber}/statement", [
                    'startDate' => $from->format('Y-m-d'),
                    'endDate' => $to->format('Y-m-d'),
                ]);
            if ($response->successful()) {
                return $response->json('transactions', []);
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('Family Bank statement exception', ['error' => $e->getMessage()]);

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
                ->get("{$this->baseUrl}/api/v1/account/{$accountNumber}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('Family Bank account verification exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function getAccessToken(): ?string
    {
        $cacheKey = 'familybank_access_token:'.sha1((string) config('services.familybank.client_id', ''));
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
                        'client_id' => config('services.familybank.client_id'),
                        'client_secret' => config('services.familybank.client_secret'),
                    ]);

                return $response->successful() ? $response->json('access_token') : null;
            } catch (\Throwable $e) {
                Log::error('Family Bank auth exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }
}
