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
        $this->cancelActiveSubscriptionIfExists($user);

        $now = now();

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'status' => $this->resolveInitialStatus($plan, $withTrial),
            'trial_ends_at' => $this->resolveTrialEndsAt($plan, $withTrial, $now),
            'current_period_start' => $now,
            'current_period_end' => $this->resolvePeriodEnd($billingCycle, $now),
        ]);
    }

    private function cancelActiveSubscriptionIfExists(User $user): void
    {
        $existingSub = $user->subscription;

        if ($existingSub && $existingSub->isActive()) {
            $this->cancel($existingSub, true);
        }
    }

    private function resolveInitialStatus(SubscriptionPlan $plan, bool $withTrial): string
    {
        return $withTrial && ! $plan->isFree() ? 'trialing' : 'active';
    }

    private function resolveTrialEndsAt(SubscriptionPlan $plan, bool $withTrial, \Illuminate\Support\Carbon $now): ?\Illuminate\Support\Carbon
    {
        return $withTrial && ! $plan->isFree() ? $now->copy()->addDays(14) : null;
    }

    private function resolvePeriodEnd(string $billingCycle, \Illuminate\Support\Carbon $now): \Illuminate\Support\Carbon
    {
        return $billingCycle === 'yearly' ? $now->copy()->addYear() : $now->copy()->addMonth();
    }

    /**
     * Change subscription plan with optional proration.
     *
     * Phase-35 PLATFORM-BILLING-1: writes a subscription_changes
     * audit row so Phase-34 MrrSnapshotService can populate the
     * expansion/contraction waterfall columns it currently ships at 0.
     *
     * Proration formula (only applied when $prorate=true and change
     * is an upgrade): prorated_amount_kes = (new.monthly - old.monthly)
     * × (remaining_days / total_days). Downgrades default to 0 — the
     * customer keeps the higher tier until period end.
     */
    public function changePlan(
        Subscription $subscription,
        SubscriptionPlan $newPlan,
        bool $prorate = true,
    ): Subscription {
        $oldPlan = $subscription->plan()->first();
        $changeType = $this->classifyChange($oldPlan, $newPlan);
        $isProratableUpgrade = $prorate && $changeType === \App\Models\SubscriptionChange::TYPE_UPGRADE;
        $proratedAmount = $this->resolveProratedAmount($subscription, $oldPlan, $newPlan, $isProratableUpgrade);

        $change = null;
        \DB::transaction(function () use ($subscription, $oldPlan, $newPlan, $changeType, $proratedAmount, &$change) {
            $change = \App\Models\SubscriptionChange::create([
                'subscription_id' => $subscription->id,
                'from_plan_id' => $oldPlan?->id ?? $newPlan->id,
                'to_plan_id' => $newPlan->id,
                'change_type' => $changeType,
                'prorated_amount_kes' => $proratedAmount,
                'effective_at' => now(),
            ]);

            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);
        });

        $this->syncGatewayPlanIfUpgrade($subscription, $newPlan, $changeType, $change);

        return $subscription->fresh();
    }

    private function resolveProratedAmount(
        Subscription $subscription,
        ?SubscriptionPlan $oldPlan,
        SubscriptionPlan $newPlan,
        bool $isProratableUpgrade,
    ): float {
        if ($isProratableUpgrade && $oldPlan) {
            return $this->computeProratedAmount($subscription, $oldPlan, $newPlan);
        }

        return 0.0;
    }

    /**
     * Phase-37 PWA-GATEWAY-1: push the plan change to Paystack on
     * upgrade when the subscription is wired to a gateway code.
     * Gateway failures are swallowed here so the user-facing
     * operation stays atomic on the DB side; gateway:proration-
     * audit nightly reconciliation catches drift via the
     * gateway_response audit column.
     */
    private function syncGatewayPlanIfUpgrade(
        Subscription $subscription,
        SubscriptionPlan $newPlan,
        string $changeType,
        ?\App\Models\SubscriptionChange $change,
    ): void {
        if (
            ! $change
            || $changeType !== \App\Models\SubscriptionChange::TYPE_UPGRADE
            || empty($subscription->paystack_subscription_code)
            || empty($newPlan->paystack_plan_code)
        ) {
            return;
        }

        try {
            $paystack = app(\App\Services\PaystackSubscriptionService::class);
            $response = $paystack->updateSubscription(
                $subscription->paystack_subscription_code,
                $newPlan->paystack_plan_code,
            );
            $change->update(['gateway_response' => $response]);
        } catch (\Throwable $e) {
            $change->update(['gateway_response' => [
                'success' => false,
                'http_status' => 0,
                'message' => $e->getMessage(),
            ]]);
        }
    }

    /**
     * Phase-35 PLATFORM-BILLING-2: register a downgrade for the
     * current period boundary instead of applying immediately.
     * Customer keeps premium until period_end; the apply-downgrades
     * cron flips plan_id at that boundary.
     */
    public function scheduleDowngradeAtPeriodEnd(
        Subscription $subscription,
        SubscriptionPlan $newPlan,
    ): \App\Models\SubscriptionChange {
        $oldPlan = $subscription->plan()->first();

        return \App\Models\SubscriptionChange::create([
            'subscription_id' => $subscription->id,
            'from_plan_id' => $oldPlan?->id ?? $newPlan->id,
            'to_plan_id' => $newPlan->id,
            'change_type' => \App\Models\SubscriptionChange::TYPE_DOWNGRADE,
            'prorated_amount_kes' => 0,
            'scheduled_for' => $subscription->current_period_end ?? now()->endOfMonth(),
            'effective_at' => null,
        ]);
    }

    private function classifyChange(?SubscriptionPlan $oldPlan, SubscriptionPlan $newPlan): string
    {
        if (! $oldPlan || $oldPlan->id === $newPlan->id) {
            return \App\Models\SubscriptionChange::TYPE_SAME;
        }

        if ((float) $newPlan->price_monthly > (float) $oldPlan->price_monthly) {
            return \App\Models\SubscriptionChange::TYPE_UPGRADE;
        }

        return \App\Models\SubscriptionChange::TYPE_DOWNGRADE;
    }

    private function computeProratedAmount(
        Subscription $subscription,
        SubscriptionPlan $oldPlan,
        SubscriptionPlan $newPlan,
    ): float {
        $periodStart = $subscription->current_period_start ?? now()->startOfMonth();
        $periodEnd = $subscription->current_period_end ?? now()->endOfMonth();
        $totalDays = max(1, (int) $periodStart->diffInDays($periodEnd));
        $remainingDays = max(0, (int) now()->diffInDays($periodEnd, false));

        $delta = (float) $newPlan->price_monthly - (float) $oldPlan->price_monthly;

        return round($delta * ($remainingDays / $totalDays), 2);
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
        return \DB::transaction(function () use ($subscription, $paymentData) {
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
        });
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
