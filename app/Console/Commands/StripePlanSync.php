<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Services\MetricsService;
use App\Services\StripeSubscriptionService;
use Illuminate\Console\Command;

/**
 * Phase-41 GATEWAY-PLAN-SYNC-1: weekly sync of SubscriptionPlan
 * rows to Stripe Prices. One-way (app → Stripe) today; Stripe-side
 * drift is detected by the price.updated webhook handler
 * (PLAN-SYNC-2) and surfaced via subscription_plan_drift gauge
 * (PLAN-SYNC-3) for operator review.
 */
class StripePlanSync extends Command
{
    protected $signature = 'stripe:plan-sync {--billing-cycle=monthly}';

    protected $description = 'Phase-41 GATEWAY-PLAN-SYNC-1: push SubscriptionPlan price changes to Stripe Prices.';

    public function handle(StripeSubscriptionService $stripe, MetricsService $metrics): int
    {
        if (! $stripe->isConfigured()) {
            $this->info('Stripe SaaS subscription service not configured — skipping.');

            return self::SUCCESS;
        }

        $billingCycle = (string) $this->option('billing-cycle');
        $plans = SubscriptionPlan::query()->where('is_active', true)->get();

        $synced = 0;
        $failed = 0;

        foreach ($plans as $plan) {
            $priceId = $stripe->createOrUpdatePlan($plan, $billingCycle);
            if ($priceId === null) {
                $failed++;
                $this->error(sprintf('plan=%d failed to sync', $plan->id));

                continue;
            }
            $plan->update(['stripe_plan_code' => $priceId]);
            $synced++;
            $this->line(sprintf('plan=%d → price=%s', $plan->id, $priceId));
        }

        $metrics->gauge('stripe_plan_sync_count', $synced);
        $metrics->gauge('stripe_plan_sync_failed_count', $failed);

        $this->info(sprintf('synced=%d failed=%d billing_cycle=%s', $synced, $failed, $billingCycle));

        return self::SUCCESS;
    }
}
