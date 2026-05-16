<?php

namespace App\Services;

use App\Exceptions\Subscription\GracePeriodExpiredException;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Support\Str;

class SubscriptionService
{
    /**
     * Create a new subscription for a user
     */
    public function create(
        User $user,
        SubscriptionPlan $plan,
        string $billingCycle = 'monthly',
        bool $withTrial = true
    ): Subscription {
        // Cancel any existing active subscription
        if ($existingSub = $user->subscription) {
            if ($existingSub->isActive()) {
                $this->cancel($existingSub, true);
            }
        }

        $now = now();
        $periodEnd = $billingCycle === 'yearly' ? $now->copy()->addYear() : $now->copy()->addMonth();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'status' => $withTrial && ! $plan->isFree() ? 'trialing' : 'active',
            'trial_ends_at' => $withTrial && ! $plan->isFree() ? $now->copy()->addDays(14) : null,
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
        ]);

        return $subscription;
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan): Subscription
    {
        // For now, changes take effect immediately
        $subscription->update([
            'plan_id' => $newPlan->id,
        ]);

        return $subscription->fresh();
    }

    /**
     * Cancel subscription.
     *
     * Phase-34 GROWTH-CHURN-1: $reason + $feedback capture the
     * voluntary/involuntary split. Unknown reasons throw — the form
     * MUST send a valid enum value (no silent NULL persistence).
     */
    public function cancel(
        Subscription $subscription,
        bool $immediately = false,
        ?string $reason = null,
        ?string $feedback = null,
    ): Subscription {
        if ($reason !== null && ! in_array($reason, Subscription::CANCEL_REASONS, true)) {
            throw new \InvalidArgumentException("Unknown cancel_reason: {$reason}");
        }

        $updateData = [
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'cancel_feedback' => $feedback,
            'ends_at' => $immediately ? now() : $subscription->current_period_end,
        ];

        if ($immediately) {
            $updateData['status'] = 'cancelled';
        }

        $subscription->update($updateData);

        return $subscription->fresh();
    }

    /**
     * Resume a cancelled subscription (only during grace period)
     */
    public function resume(Subscription $subscription): Subscription
    {
        if (! $subscription->onGracePeriod()) {
            throw new GracePeriodExpiredException($subscription->id);
        }

        $subscription->update([
            'cancelled_at' => null,
            'ends_at' => null,
        ]);

        return $subscription->fresh();
    }

    /**
     * Record a successful payment and extend subscription
     */
    public function recordPayment(Subscription $subscription, array $paymentData): SubscriptionPayment
    {
        $payment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'KES',
            'status' => 'successful',
            'payment_method' => $paymentData['payment_method'],
            'reference' => $paymentData['reference'] ?? $this->generateReference(),
            'paystack_reference' => $paymentData['paystack_reference'] ?? null,
            'paystack_response' => $paymentData['paystack_response'] ?? null,
            'paid_at' => now(),
        ]);

        // Extend subscription period
        $now = now();
        $periodEnd = $subscription->billing_cycle === 'yearly'
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        $subscription->update([
            'status' => 'active',
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
            'cancelled_at' => null,
            'ends_at' => null,
        ]);

        return $payment;
    }

    /**
     * Check if subscription needs renewal
     */
    public function needsRenewal(Subscription $subscription): bool
    {
        if (! $subscription->current_period_end) {
            return false;
        }

        // Needs renewal if within 3 days of period end
        return $subscription->current_period_end->diffInDays(now()) <= 3;
    }

    /**
     * Mark subscription as past due
     */
    public function markPastDue(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'past_due']);

        return $subscription->fresh();
    }

    /**
     * Update usage tracking for a feature
     */
    public function updateUsage(User $user, string $feature, int $quantity): UsageRecord
    {
        return UsageRecord::setUsage($user->id, $feature, $quantity);
    }

    /**
     * Increment usage for a feature
     */
    public function incrementUsage(User $user, string $feature, int $amount = 1): UsageRecord
    {
        return UsageRecord::incrementUsage($user->id, $feature, $amount);
    }

    /**
     * Get the price for a plan based on billing cycle
     */
    public function getPriceForCycle(SubscriptionPlan $plan, string $billingCycle): float
    {
        return $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
    }

    /**
     * Generate a unique payment reference
     */
    protected function generateReference(): string
    {
        return 'SUB-'.time().'-'.strtoupper(Str::random(6));
    }

    /**
     * Assign free plan to a new landlord
     */
    public function assignFreePlan(User $user): ?Subscription
    {
        $freePlan = SubscriptionPlan::free();

        if (! $freePlan) {
            return null;
        }

        return $this->create($user, $freePlan, 'monthly', false);
    }
}
