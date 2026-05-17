<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\StripeCustomer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Phase-42 METHODS-1: idempotent users.id <-> Stripe Customer
 * mapping. ensureCustomer is the canonical entry point — it
 * returns the persisted stripe_customer_id, creating it in
 * Stripe + persisting the mapping if missing. Subsequent calls
 * return the existing mapping without hitting Stripe.
 *
 * Uses the system-wide Stripe credentials (StripeSubscriptionService
 * neighbourhood — same SaaS billing plane as Phase 40's
 * SubscriptionService). Per-landlord rent collection does NOT
 * touch this surface.
 */
class StripeCustomerService
{
    protected string $secretKey;

    protected ?StripeClient $client = null;

    public function __construct()
    {
        $this->secretKey = (string) (Setting::getSystem('stripe_secret_key') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    protected function client(): StripeClient
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'StripeCustomerService is not configured. Set system stripe_secret_key.'
            );
        }
        if ($this->client === null) {
            $this->client = new StripeClient($this->secretKey);
        }

        return $this->client;
    }

    public function findMapping(User $user): ?StripeCustomer
    {
        return StripeCustomer::query()->where('user_id', $user->id)->first();
    }

    /**
     * Idempotent: returns existing mapping or creates a new one.
     * When Stripe isn't configured, returns null without persisting
     * anything (lets tests + dev environments degrade gracefully).
     */
    public function ensureCustomer(User $user): ?string
    {
        $existing = $this->findMapping($user);
        if ($existing !== null) {
            return $existing->stripe_customer_id;
        }

        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $customer = $this->client()->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'platform' => 'PropManager',
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('StripeCustomerService customers.create failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return DB::transaction(function () use ($user, $customer) {
            $mapping = StripeCustomer::create([
                'user_id' => $user->id,
                'stripe_customer_id' => $customer->id,
            ]);

            return $mapping->stripe_customer_id;
        });
    }

    /**
     * Phase-42 METHODS-3: attach a PaymentMethod (collected via a
     * SetupIntent on the frontend) to the persisted Stripe Customer
     * and optionally make it the default. Returns true on success.
     */
    public function ensurePaymentMethodAttached(StripeCustomer $customer, string $paymentMethodId, bool $makeDefault = false): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $this->client()->paymentMethods->attach($paymentMethodId, ['customer' => $customer->stripe_customer_id]);
            if ($makeDefault) {
                $this->client()->customers->update($customer->stripe_customer_id, [
                    'invoice_settings' => ['default_payment_method' => $paymentMethodId],
                ]);
                $customer->update(['default_payment_method_id' => $paymentMethodId]);
            }

            return true;
        } catch (ApiErrorException $e) {
            Log::warning('StripeCustomerService ensurePaymentMethodAttached failed', [
                'customer_id' => $customer->stripe_customer_id,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function detachPaymentMethod(string $paymentMethodId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $this->client()->paymentMethods->detach($paymentMethodId);

            return true;
        } catch (ApiErrorException $e) {
            Log::warning('StripeCustomerService detachPaymentMethod failed', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
