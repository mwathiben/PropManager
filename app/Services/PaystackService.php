<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected $secretKey;

    protected $publicKey;

    protected $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
        $this->baseUrl = 'https://api.paystack.co';
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
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/transaction/initialize', [
                'email' => $data['email'],
                'amount' => $data['amount'] * 100, // Convert to kobo
                'reference' => $data['reference'],
                'callback_url' => $data['callback_url'] ?? route('payments.callback'),
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack initialization failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack initialization exception', ['error' => $e->getMessage()]);

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
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/transaction/verify/'.$reference);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack verification failed', [
                'reference' => $reference,
                'response' => $response->body(),
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
        try {
            $response = Http::withHeaders([
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
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack subaccount creation failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack subaccount creation exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Update a Paystack subaccount
     */
    public function updateSubaccount(string $subaccountCode, array $data): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->put($this->baseUrl.'/subaccount/'.$subaccountCode, $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack subaccount update failed', [
                'subaccount_code' => $subaccountCode,
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack subaccount update exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fetch Paystack subaccount details
     */
    public function getSubaccount(string $subaccountCode): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/subaccount/'.$subaccountCode);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack subaccount fetch failed', [
                'subaccount_code' => $subaccountCode,
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack subaccount fetch exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * List available banks for subaccount setup
     */
    public function listBanks(string $country = 'kenya'): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/bank', [
                'country' => $country,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack list banks failed', [
                'country' => $country,
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack list banks exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Resolve/verify bank account number
     */
    public function resolveAccountNumber(string $accountNumber, string $bankCode): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack account resolution failed', [
                'account_number' => substr($accountNumber, -4),
                'bank_code' => $bankCode,
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack account resolution exception', ['error' => $e->getMessage()]);

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
            $payload = [
                'email' => $data['email'],
                'amount' => $data['amount'] * 100, // Convert to kobo
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

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/transaction/initialize', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack split transaction init failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack split transaction exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function refundTransaction(string $reference, ?float $amount = null): ?array
    {
        try {
            $data = ['transaction' => $reference];

            if ($amount !== null) {
                $data['amount'] = (int) ($amount * 100);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/refund', $data);

            if ($response->successful() && $response->json('status')) {
                return $response->json('data');
            }

            Log::error('Paystack refund failed', [
                'reference' => $reference,
                'response' => $response->json(),
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
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/refund/'.$refundId);

            if ($response->successful()) {
                return $response->json('data');
            }

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

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/refund', $params);

            if ($response->successful()) {
                return $response->json('data');
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack list refunds exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
