<?php

namespace App\Services\Banking;

use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KcbBankService implements BankServiceInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.kcb.sandbox')
            ? 'https://sandbox.kcbgroup.com'
            : 'https://api.kcbgroup.com';
    }

    public function validateWebhook(string $signature, string $payload): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $payload, config('services.kcb.webhook_secret'), true));

        return hash_equals($expected, $signature);
    }

    public function parsePaymentNotification(array $payload): PaymentNotification
    {
        return new PaymentNotification(
            bankCode: 'kcb',
            transactionId: $payload['TransactionID'],
            amount: (float) $payload['Amount'],
            accountNumber: $payload['DebitAccountNumber'],
            reference: $payload['Narration'] ?? null,
            senderName: $payload['DebitAccountName'] ?? null,
            senderPhone: null,
            transactionDate: new DateTime($payload['TransactionDate']),
            rawPayload: $payload
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
                ->get("{$this->baseUrl}/account/v1/statement", [
                    'accountNumber' => $accountNumber,
                    'startDate' => $from->format('Y-m-d'),
                    'endDate' => $to->format('Y-m-d'),
                ]);

            if ($response->successful()) {
                return $response->json('transactions', []);
            }

            Log::error('KCB Bank statement fetch failed', ['response' => $response->json()]);

            return [];
        } catch (\Exception $e) {
            Log::error('KCB Bank statement exception', ['error' => $e->getMessage()]);

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
                ->post("{$this->baseUrl}/account/v1/enquiry", [
                    'accountNumber' => $accountNumber,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('KCB Bank account verification exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function getAccessToken(): ?string
    {
        // CONC-11: derive cache key from the credential pair so a future
        // per-tenant credential model doesn't leak one tenant's token to
        // another. Today client_id is global; the suffix still keys cleanly
        // on whatever the live config returns.
        $credentialKey = sha1((string) config('services.kcb.client_id'));
        $cacheKey = "kcb_access_token:{$credentialKey}";

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Serialize the OAuth fetch so a thundering herd of cache misses
        // doesn't make N concurrent /oauth/token calls.
        $lock = Cache::lock("{$cacheKey}:fetch", 10);
        try {
            if (! $lock->block(5)) {
                return Cache::get($cacheKey);
            }

            return Cache::remember($cacheKey, 3500, function () {
                try {
                    $response = Http::connectTimeout(3)->timeout(15)->asForm()->post("{$this->baseUrl}/oauth/token", [
                        'grant_type' => 'client_credentials',
                        'client_id' => config('services.kcb.client_id'),
                        'client_secret' => config('services.kcb.client_secret'),
                    ]);

                    if ($response->successful()) {
                        return $response->json('access_token');
                    }

                    Log::error('KCB Bank auth failed', ['response' => $response->json()]);

                    return null;
                } catch (\Exception $e) {
                    Log::error('KCB Bank auth exception', ['error' => $e->getMessage()]);

                    return null;
                }
            });
        } finally {
            optional($lock)->release();
        }
    }
}
