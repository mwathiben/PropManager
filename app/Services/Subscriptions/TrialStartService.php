<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Phase-60 TRIAL-DEPTH-1: mint a 14-day trial subscription for new
 * landlord signups. Today the trial_ends_at column exists since
 * Phase 34 but is never populated at signup time — new landlords
 * hit feature gates immediately.
 *
 * Trial plan defaults to config('subscriptions.trial_plan_slug',
 * 'starter') — operator-tunable per environment. Idempotent: if the
 * user already has an active subscription, returns it unchanged.
 */
class TrialStartService
{
    public function startTrialFor(User $landlord, int $days = 14): ?Subscription
    {
        if (! $landlord->isScopeOwner()) {
            return null;
        }

        if ($landlord->subscription !== null) {
            return $landlord->subscription;
        }

        $slug = (string) config('subscriptions.trial_plan_slug', 'starter');
        $plan = SubscriptionPlan::query()->where('slug', $slug)->first();
        if (! $plan) {
            Log::warning('trial_start_no_plan', ['slug' => $slug, 'user_id' => $landlord->id]);

            return null;
        }

        return Subscription::create([
            'user_id' => $landlord->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays($days),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays($days),
        ]);
    }
}
