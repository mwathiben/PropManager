<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Traits\LogsExternalRequests;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaystackSubscriptionService
{
    use LogsExternalRequests;

    protected string $secretKey;

    protected string $publicKey;

    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = Setting::getSystem('paystack_secret_key') ?? '';
        $this->publicKey = Setting::getSystem('paystack_public_key') ?? '';
    }

    public function initializePayment(User $user, SubscriptionPlan $plan, string $billingCycle): ?array
    {
        $amount = $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        $reference = 'SUB-'.time().'-'.strtoupper(Str::random(6));

        try {
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
                ])->post($this->baseUrl.'/transaction/initialize', [
                    'email' => $user->email,
                    'amount' => (int) round($amount * 100),
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
                ]));

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] ?? false) {
                    return $data['data'];
                }
            }

            Log::error('Paystack subscription initialization failed', [
                'status' => $response->status(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack subscription initialization connection failed', [
                'error' => $e->getMessage(),
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

    public function verifyPayment(string $reference): ?array
    {
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
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Paystack verification connection failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
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

    public function createPlan(SubscriptionPlan $plan, string $interval = 'monthly'): ?string
    {
        $amount = $interval === 'annually' ? $plan->price_yearly : $plan->price_monthly;

        try {
            $response = $this->timedHttpRequest('paystack', '/plan', fn () => Http::timeout($this->timeoutSeconds())
                ->retry($this->retryAttempts(), function (int $attempt) {
                    $base = (int) config('payments.gateways.paystack.retry_backoff_base', 2);

                    return $this->retryDelayMs() * ($base ** ($attempt - 1));
                }, function ($exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl.'/plan', [
                    'name' => $plan->name.' ('.ucfirst($interval).')',
                    'amount' => (int) round($amount * 100),
                    'interval' => $interval,
                    'currency' => $plan->currency,
                ]));

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

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->secretKey) && ! empty($this->publicKey);
    }
}
