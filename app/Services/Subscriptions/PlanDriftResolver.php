<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\DriftResolveMode;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDriftLog;
use App\Services\StripeSubscriptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase-42 PLAN-SYNC-AUTO-1/2: resolves a detected price drift
 * according to the plan's drift_resolve_mode. Always writes an
 * append-only row to subscription_plan_drift_log first; then
 * branches on the mode.
 */
final class PlanDriftResolver
{
    public function __construct(private readonly StripeSubscriptionService $stripeSubscriptionService) {}

    public function resolve(SubscriptionPlan $plan, int $stripePriceCents, string $stripePriceId): SubscriptionPlanDriftLog
    {
        $mode = $plan->drift_resolve_mode instanceof DriftResolveMode
            ? $plan->drift_resolve_mode
            : DriftResolveMode::ManualReview;

        $appPriceCents = (int) round((float) $plan->price_monthly * 100);

        $log = SubscriptionPlanDriftLog::create([
            'subscription_plan_id' => $plan->id,
            'stripe_price_id' => $stripePriceId,
            'app_price_cents' => $appPriceCents,
            'stripe_price_cents' => $stripePriceCents,
            'drift_resolve_mode_at_time' => $mode->value,
            'resolution' => SubscriptionPlanDriftLog::RESOLUTION_PENDING,
            'detected_at' => Carbon::now(),
        ]);

        try {
            match ($mode) {
                DriftResolveMode::ManualReview => $this->markManualPending($log),
                DriftResolveMode::AlwaysAppWins => $this->resolveAppWins($plan, $log),
                DriftResolveMode::AlwaysStripeWins => $this->resolveStripeWins($plan, $log, $stripePriceCents),
            };
        } catch (Throwable $e) {
            Log::warning('PlanDriftResolver failed', [
                'plan_id' => $plan->id,
                'mode' => $mode->value,
                'error' => $e->getMessage(),
            ]);
        }

        return $log->fresh() ?? $log;
    }

    private function markManualPending(SubscriptionPlanDriftLog $log): void
    {
        $log->resolution = SubscriptionPlanDriftLog::RESOLUTION_MANUAL_PENDING;
        $log->save();
    }

    private function resolveAppWins(SubscriptionPlan $plan, SubscriptionPlanDriftLog $log): void
    {
        if ($this->stripeSubscriptionService->isConfigured()) {
            $this->stripeSubscriptionService->createOrUpdatePlan($plan, 'monthly');
        }
        $log->resolution = SubscriptionPlanDriftLog::RESOLUTION_APP_WINS;
        $log->resolved_at = Carbon::now();
        $log->save();
    }

    private function resolveStripeWins(SubscriptionPlan $plan, SubscriptionPlanDriftLog $log, int $stripePriceCents): void
    {
        $plan->price_monthly = number_format($stripePriceCents / 100, 2, '.', '');
        $plan->save();
        $log->resolution = SubscriptionPlanDriftLog::RESOLUTION_STRIPE_WINS;
        $log->resolved_at = Carbon::now();
        $log->save();
    }
}
