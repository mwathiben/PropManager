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

    /**
     * Phase-37 PWA-GATEWAY-1: change the active plan on an existing
     * Paystack subscription. PUT /subscription/:code/plan, returns
     * the full Paystack envelope ({status, message, data}) so the
     * SubscriptionChange.gateway_response audit row can capture it.
     */
    public function updateSubscription(string $subscriptionCode, string $newPlanCode): array
    {
        try {
            $response = $this->timedHttpRequest(
                'paystack',
                '/subscription/'.$subscriptionCode.'/plan',
                fn () => Http::timeout($this->timeoutSeconds())
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$this->secretKey,
                        'Content-Type' => 'application/json',
                    ])->put($this->baseUrl.'/subscription/'.$subscriptionCode.'/plan', [
                        'plan' => $newPlanCode,
                    ]),
            );

            $body = $response->json();

            return [
                'success' => $response->successful() && ($body['status'] ?? false),
                'http_status' => $response->status(),
                'message' => $body['message'] ?? null,
                'data' => $body['data'] ?? null,
                'requested_plan' => $newPlanCode,
            ];
        } catch (\Throwable $e) {
            Log::error('Paystack updateSubscription error', [
                'error' => $e->getMessage(),
                'subscription_code' => $subscriptionCode,
                'plan_code' => $newPlanCode,
            ]);

            return [
                'success' => false,
                'http_status' => 0,
                'message' => $e->getMessage(),
                'data' => null,
                'requested_plan' => $newPlanCode,
            ];
        }
    }

    /**
     * Phase-37 PWA-GATEWAY-1: disable a Paystack subscription
     * (POST /subscription/disable with {code, token}). The
     * Paystack-provided email_token is read from sub.metadata so
     * callers don't have to plumb it.
     */
    public function disableSubscription(string $subscriptionCode, string $emailToken): array
    {
        try {
            $response = $this->timedHttpRequest(
                'paystack',
                '/subscription/disable',
                fn () => Http::timeout($this->timeoutSeconds())
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$this->secretKey,
                        'Content-Type' => 'application/json',
                    ])->post($this->baseUrl.'/subscription/disable', [
                        'code' => $subscriptionCode,
                        'token' => $emailToken,
                    ]),
            );

            $body = $response->json();

            return [
                'success' => $response->successful() && ($body['status'] ?? false),
                'http_status' => $response->status(),
                'message' => $body['message'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Paystack disableSubscription error', [
                'error' => $e->getMessage(),
                'subscription_code' => $subscriptionCode,
            ]);

            return [
                'success' => false,
                'http_status' => 0,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Phase-37 PWA-GATEWAY-1: read the current Paystack-side state
     * of a subscription. The gateway:proration-audit reconciliation
     * cron compares the returned plan code vs the local to_plan_id
     * on the latest SubscriptionChange row.
     */
    public function syncFromGateway(string $subscriptionCode): array
    {
        try {
            $response = $this->timedHttpRequest(
                'paystack',
                '/subscription/'.$subscriptionCode,
                fn () => Http::timeout($this->timeoutSeconds())
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$this->secretKey,
                    ])->get($this->baseUrl.'/subscription/'.$subscriptionCode),
            );

            $body = $response->json();

            return [
                'success' => $response->successful() && ($body['status'] ?? false),
                'http_status' => $response->status(),
                'status' => $body['data']['status'] ?? null,
                'plan_code' => $body['data']['plan']['plan_code'] ?? null,
                'data' => $body['data'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Paystack syncFromGateway error', [
                'error' => $e->getMessage(),
                'subscription_code' => $subscriptionCode,
            ]);

            return [
                'success' => false,
                'http_status' => 0,
                'status' => null,
                'plan_code' => null,
                'data' => null,
            ];
        }
    }
}
