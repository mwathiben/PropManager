<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Events\PlanChanged;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanChange;
use App\Models\User;
use App\Services\StripeSubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase-60 PLAN-CHANGE-1: self-serve plan upgrade/downgrade. Wraps
 * StripeSubscriptionService::updateSubscription for the Stripe side
 * and rewrites the local Subscription.plan_id inside a transaction.
 * Always writes a SubscriptionPlanChange audit row, even on Stripe
 * failure, so support can trace user intent vs Stripe state.
 */
class PlanChangeService
{
    public function __construct(
        private readonly StripeSubscriptionService $stripe,
    ) {}

    public function changePlan(
        Subscription $subscription,
        SubscriptionPlan $newPlan,
        User $initiatedBy,
        string $prorationBehaviour = 'create_prorations',
    ): SubscriptionPlanChange {
        $fromPlan = $subscription->plan;

        return DB::transaction(function () use ($subscription, $newPlan, $fromPlan, $initiatedBy, $prorationBehaviour) {
            $audit = SubscriptionPlanChange::create([
                'subscription_id' => $subscription->id,
                'from_plan_id' => $fromPlan->id,
                'to_plan_id' => $newPlan->id,
                'initiated_by' => $initiatedBy->id,
                'proration_behaviour' => $prorationBehaviour,
                'stripe_succeeded' => false,
            ]);

            $stripeSucceeded = $this->maybeUpdateStripe($subscription, $newPlan, $audit);

            if ($stripeSucceeded) {
                $subscription->plan_id = $newPlan->id;
                $subscription->save();

                PlanChanged::dispatch($subscription, $fromPlan, $newPlan, $initiatedBy);
            }

            $audit->stripe_succeeded = $stripeSucceeded;
            $audit->save();

            return $audit;
        });
    }

    private function maybeUpdateStripe(
        Subscription $subscription,
        SubscriptionPlan $newPlan,
        SubscriptionPlanChange $audit,
    ): bool {
        if (! $subscription->stripe_subscription_code) {
            // No Stripe binding — local-only change is acceptable for
            // dev/Paystack/null-gateway flows.
            return true;
        }

        if (! $newPlan->stripe_plan_code) {
            $audit->error_message = 'Target plan has no stripe_plan_code; refusing Stripe update.';

            return false;
        }

        try {
            $result = $this->stripe->updateSubscription(
                $subscription->stripe_subscription_code,
                $newPlan->stripe_plan_code,
            );

            if (! ($result['success'] ?? false)) {
                $audit->error_message = (string) ($result['message'] ?? 'unknown stripe error');

                return false;
            }

            return true;
        } catch (Throwable $e) {
            $audit->error_message = $e->getMessage();
            Log::error('plan_change_stripe_failed', [
                'subscription_id' => $subscription->id,
                'to_plan_id' => $newPlan->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
