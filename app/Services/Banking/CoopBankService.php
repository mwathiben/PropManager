<?php

namespace App\Services\Banking;

use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoopBankService implements BankServiceInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.coop.sandbox')
            ? 'https://developer.co-opbank.co.ke:8243'
            : 'https://openapi.co-opbank.co.ke:8243';
    }

    public function validateWebhook(string $signature, string $payload): bool
    {
        $expected = hash_hmac('sha256', $payload, config('services.coop.webhook_secret'));

        return hash_equals($expected, $signature);
    }

    public function parsePaymentNotification(array $payload): PaymentNotification
    {
        return new PaymentNotification(
            bankCode: 'coop',
            transactionId: $payload['TransactionID'] ?? $payload['MessageReference'],
            amount: (float) ($payload['Amount'] ?? $payload['TransactionAmount']),
            accountNumber: $payload['AccountNumber'] ?? $payload['VirtualAccountNumber'],
            reference: $payload['Narration'] ?? $payload['TransactionReference'] ?? null,
            senderName: $payload['SenderName'] ?? null,
            senderPhone: $payload['SenderPhone'] ?? null,
            transactionDate: new DateTime($payload['TransactionDate'] ?? 'now'),
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
                ->post("{$this->baseUrl}/Enquiry/MiniStatement/Account/1.0.0", [
                    'MessageReference' => 'STMT_'.bin2hex(random_bytes(8)),
                    'AccountNumber' => $accountNumber,
                ]);

            if ($response->successful()) {
                return $response->json('Transactions', []);
            }

            Log::error('Co-op Bank statement fetch failed', ['response' => $response->json()]);

            return [];
        } catch (\Exception $e) {
            Log::error('Co-op Bank statement exception', ['error' => $e->getMessage()]);

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
                ->post("{$this->baseUrl}/Enquiry/AccountBalance/1.0.0", [
                    'MessageReference' => 'VERIFY_'.bin2hex(random_bytes(8)),
                    'AccountNumber' => $accountNumber,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Co-op Bank account verification exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function createVirtualAccount(string $tenantId, string $tenantName): ?string
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/VirtualAccount/1.0.0/CreateVirtualAccount", [
                    // CONC-8: collision-safe random reference.
                    'MessageReference' => 'VA_'.bin2hex(random_bytes(8)),
                    'AccountName' => $tenantName,
                    'AccountType' => 'COLLECTION',
                    'ExternalReference' => $tenantId,
                ]);

            if ($response->successful()) {
                return $response->json('VirtualAccountNumber');
            }

            Log::error('Co-op Bank virtual account creation failed', ['response' => $response->json()]);

            return null;
        } catch (\Exception $e) {
            Log::error('Co-op Bank virtual account exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('coop_access_token', 3500, function () {
            try {
                $credentials = base64_encode(
                    config('services.coop.consumer_key').':'.config('services.coop.consumer_secret')
                );

                $response = Http::withHeaders([
                    'Authorization' => 'Basic '.$credentials,
                ])->asForm()->post("{$this->baseUrl}/token", [
                    'grant_type' => 'client_credentials',
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('Co-op Bank auth failed', ['response' => $response->json()]);

                return null;
            } catch (\Exception $e) {
                Log::error('Co-op Bank auth exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }
}
