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
        return Cache::remember('kcb_access_token', 3500, function () {
            try {
                $response = Http::asForm()->post("{$this->baseUrl}/oauth/token", [
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
    }
}
