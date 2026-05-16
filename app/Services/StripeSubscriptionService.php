<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Phase-40 GATEWAY-STRIPE-2: PropManager's own SaaS billing via
 * Stripe. SYSTEM-WIDE credentials (Setting::getSystem) — NOT the
 * per-landlord StripeService. See docs/runbooks/payments.md
 * "Per-landlord vs system-wide gateway credentials" for the rule.
 *
 * Mirrors PaystackSubscriptionService surface so SubscriptionService
 * + admin UIs can route between gateways uniformly.
 */
class StripeSubscriptionService
{
    protected string $secretKey;

    protected string $publicKey;

    protected string $webhookSecret;

    protected ?StripeClient $client = null;

    public function __construct()
    {
        $this->secretKey = (string) (Setting::getSystem('stripe_secret_key') ?? '');
        $this->publicKey = (string) (Setting::getSystem('stripe_publishable_key') ?? '');
        $this->webhookSecret = (string) (Setting::getSystem('stripe_webhook_secret') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->publicKey !== '';
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey === '' ? null : $this->publicKey;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret === '' ? null : $this->webhookSecret;
    }

    protected function client(): StripeClient
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'StripeSubscriptionService is not configured. Set system Stripe credentials via Setting::setSystem.'
            );
        }
        if ($this->client === null) {
            $this->client = new StripeClient($this->secretKey);
        }

        return $this->client;
    }

    /**
     * Create or update a Stripe Price for the given subscription plan.
     * Returns the Price id (stripe_plan_code).
     */
    public function createOrUpdatePlan(SubscriptionPlan $plan, string $billingCycle = 'monthly'): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $amountMinor = (int) round(($billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly) * 100);
        $currency = strtolower($plan->currency ?? 'usd');
        $interval = $billingCycle === 'yearly' ? 'year' : 'month';

        try {
            $product = $this->client()->products->create([
                'name' => $plan->name.' ('.$billingCycle.')',
                'metadata' => ['plan_id' => $plan->id],
            ]);
            $price = $this->client()->prices->create([
                'product' => $product->id,
                'unit_amount' => $amountMinor,
                'currency' => $currency,
                'recurring' => ['interval' => $interval],
                'metadata' => ['plan_id' => $plan->id, 'billing_cycle' => $billingCycle],
            ]);

            return $price->id;
        } catch (ApiErrorException $e) {
            Log::warning('stripe createOrUpdatePlan failed', ['error' => $e->getMessage(), 'plan_id' => $plan->id]);

            return null;
        }
    }

    /**
     * Initialize a Checkout Session for the given user + plan.
     * Returns the hosted-checkout URL.
     */
    public function initializeCheckout(User $user, SubscriptionPlan $plan, string $billingCycle = 'monthly'): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $priceId = $plan->stripe_plan_code ?? $this->createOrUpdatePlan($plan, $billingCycle);
        if (! $priceId) {
            return null;
        }

        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'subscription',
                'line_items' => [['price' => $priceId, 'quantity' => 1]],
                'customer_email' => $user->email,
                'success_url' => route('subscription.callback').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.index'),
                'metadata' => ['user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => $billingCycle],
            ]);

            return [
                'status' => true,
                'reference' => $session->id,
                'url' => $session->url,
                'subscription_code' => $session->subscription,
            ];
        } catch (ApiErrorException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function syncFromGateway(string $subscriptionCode): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Stripe not configured'];
        }
        try {
            $sub = $this->client()->subscriptions->retrieve($subscriptionCode);

            return [
                'success' => true,
                'status' => $sub->status,
                'current_period_end' => $sub->current_period_end,
                'cancel_at_period_end' => $sub->cancel_at_period_end,
                'raw' => $sub->toArray(),
            ];
        } catch (ApiErrorException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateSubscription(string $subscriptionCode, string $newPriceId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Stripe not configured'];
        }
        try {
            $sub = $this->client()->subscriptions->retrieve($subscriptionCode);
            $itemId = $sub->items->data[0]->id ?? null;
            if (! $itemId) {
                return ['success' => false, 'message' => 'No subscription items found'];
            }
            $updated = $this->client()->subscriptions->update($subscriptionCode, [
                'items' => [['id' => $itemId, 'price' => $newPriceId]],
                'proration_behavior' => 'create_prorations',
            ]);

            return ['success' => true, 'raw' => $updated->toArray()];
        } catch (ApiErrorException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function disableSubscription(string $subscriptionCode): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Stripe not configured'];
        }
        try {
            $sub = $this->client()->subscriptions->cancel($subscriptionCode);

            return ['success' => true, 'status' => $sub->status];
        } catch (ApiErrorException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyWebhookSignature(string $rawPayload, string $sigHeader): bool
    {
        if ($this->webhookSecret === '') {
            return false;
        }
        try {
            Webhook::constructEvent($rawPayload, $sigHeader, $this->webhookSecret);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
