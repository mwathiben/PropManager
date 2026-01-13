<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaystackSubscriptionService
{
    protected string $secretKey;

    protected string $publicKey;

    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        // Try database first, then fall back to config/env
        $this->secretKey = Setting::getSystem('paystack_secret_key')
            ?? config('services.paystack.secret_key')
            ?? '';

        $this->publicKey = Setting::getSystem('paystack_public_key')
            ?? config('services.paystack.public_key')
            ?? '';
    }

    /**
     * Initialize a subscription payment
     */
    public function initializePayment(User $user, SubscriptionPlan $plan, string $billingCycle): ?array
    {
        $amount = $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        $reference = 'SUB-'.time().'-'.strtoupper(Str::random(6));

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/transaction/initialize', [
                'email' => $user->email,
                'amount' => (int) ($amount * 100), // Convert to kobo/cents
                'reference' => $reference,
                'callback_url' => route('subscription.callback'),
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'type' => 'subscription',
                    'custom_fields' => [
                        [
                            'display_name' => 'Plan',
                            'variable_name' => 'plan_name',
                            'value' => $plan->name,
                        ],
                        [
                            'display_name' => 'Billing Cycle',
                            'variable_name' => 'billing_cycle',
                            'value' => ucfirst($billingCycle),
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] ?? false) {
                    return $data['data'];
                }
            }

            Log::error('Paystack subscription initialization failed', [
                'response' => $response->json(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack subscription initialization error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]);

            return null;
        }
    }

    /**
     * Verify a payment transaction
     */
    public function verifyPayment(string $reference): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/transaction/verify/'.$reference);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack verification failed', [
                'response' => $response->json(),
                'reference' => $reference,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack verification error', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return null;
        }
    }

    /**
     * Create a Paystack plan for recurring payments
     */
    public function createPlan(SubscriptionPlan $plan, string $interval = 'monthly'): ?string
    {
        $amount = $interval === 'annually' ? $plan->price_yearly : $plan->price_monthly;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/plan', [
                'name' => $plan->name.' ('.ucfirst($interval).')',
                'amount' => (int) ($amount * 100),
                'interval' => $interval,
                'currency' => $plan->currency,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] ?? false) {
                    return $data['data']['plan_code'];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack plan creation error', [
                'error' => $e->getMessage(),
                'plan_id' => $plan->id,
            ]);

            return null;
        }
    }

    /**
     * Get the Paystack public key
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Check if Paystack is configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->secretKey) && ! empty($this->publicKey);
    }
}
