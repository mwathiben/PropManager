<?php

namespace App\Services\Banking;

use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EquityBankService implements BankServiceInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.equity.sandbox')
            ? 'https://uat.finserve.africa'
            : 'https://api.finserve.africa';
    }

    public function validateWebhook(string $signature, string $payload): bool
    {
        $expected = hash_hmac('sha256', $payload, config('services.equity.webhook_secret'));

        return hash_equals($expected, $signature);
    }

    public function parsePaymentNotification(array $payload): PaymentNotification
    {
        return new PaymentNotification(
            bankCode: 'equity',
            transactionId: $payload['transactionId'],
            amount: (float) $payload['amount'],
            accountNumber: $payload['accountNumber'],
            reference: $payload['reference'] ?? null,
            senderName: $payload['senderName'] ?? null,
            senderPhone: $payload['senderMobile'] ?? null,
            transactionDate: new DateTime($payload['transactionDate']),
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
                ->get("{$this->baseUrl}/account/v2/accounts/{$accountNumber}/statement", [
                    'fromDate' => $from->format('Y-m-d'),
                    'toDate' => $to->format('Y-m-d'),
                ]);

            if ($response->successful()) {
                return $response->json('transactions', []);
            }

            Log::error('Equity Bank statement fetch failed', ['response' => $response->json()]);

            return [];
        } catch (\Exception $e) {
            Log::error('Equity Bank statement exception', ['error' => $e->getMessage()]);

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
                ->get("{$this->baseUrl}/account/v2/accounts/{$accountNumber}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Equity Bank account verification exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('equity_access_token', 3500, function () {
            try {
                $response = Http::asForm()->post("{$this->baseUrl}/authentication/api/v3/authenticate/merchant", [
                    'merchantCode' => config('services.equity.merchant_code'),
                    'consumerSecret' => config('services.equity.consumer_secret'),
                ]);

                if ($response->successful()) {
                    return $response->json('accessToken');
                }

                Log::error('Equity Bank auth failed', ['response' => $response->json()]);

                return null;
            } catch (\Exception $e) {
                Log::error('Equity Bank auth exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }
}
