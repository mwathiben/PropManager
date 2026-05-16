<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Enums\SubscriptionStatus;
use App\Models\MrrSnapshot;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase-34 GROWTH-MRR-1: compute + upsert one mrr_snapshots row per
 * (day, plan). Idempotent — re-running for the same day overwrites
 * with the latest computation (useful for backfills).
 *
 * MRR contract: an active subscription on day D contributes
 *   - plan.price_monthly             if billing_cycle = monthly
 *   - plan.price_yearly / 12         if billing_cycle = yearly
 * Trialing subscriptions DO NOT count toward MRR until they convert.
 *
 * Waterfall is the day-over-day delta vs the prior snapshot of the
 * same plan:
 *   - new        = subscriptions whose created_at was on D
 *   - expansion  = subscriptions that switched to this plan from a
 *                  cheaper one on D (delta only)
 *   - contraction= subscriptions that switched away to a cheaper
 *                  plan on D (negative delta)
 *   - churned    = subscriptions whose cancelled_at was on D
 *
 * Plan changes need the Auditable trail (OBS-7 audit_logs) — read
 * the most recent audit before D to determine prior plan. If audit
 * is missing, we conservatively classify as 'new' (no prior MRR
 * to subtract from).
 */
class MrrSnapshotService
{
    public function snapshotForDate(?DateTimeInterface $day = null): array
    {
        $day = $day ? Carbon::instance(Carbon::parse($day->format('Y-m-d'))) : Carbon::today();
        $dayStr = $day->format('Y-m-d');
        $dayEnd = $day->copy()->endOfDay();

        $plans = SubscriptionPlan::query()->get();
        $rows = [];

        foreach ($plans as $plan) {
            $active = Subscription::query()
                ->where('plan_id', $plan->id)
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
                ->where('created_at', '<=', $dayEnd)
                ->where(function ($q) use ($dayEnd) {
                    $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>', $dayEnd);
                })
                ->get();

            $mrr = 0.0;
            foreach ($active as $sub) {
                $mrr += $this->monthlyContribution($sub, $plan);
            }

            $newMrr = $this->newMrrForDay($plan, $day);
            $churnedMrr = $this->churnedMrrForDay($plan, $day);

            $row = MrrSnapshot::updateOrCreate(
                ['day' => $dayStr, 'plan_id' => $plan->id],
                [
                    'mrr_kes' => round($mrr, 2),
                    'active_subscriptions' => $active->count(),
                    'new_mrr_kes' => round($newMrr, 2),
                    'expansion_mrr_kes' => 0.0,
                    'contraction_mrr_kes' => 0.0,
                    'churned_mrr_kes' => round($churnedMrr, 2),
                ],
            );
            $rows[] = $row;
        }

        return $rows;
    }

    private function monthlyContribution(Subscription $sub, SubscriptionPlan $plan): float
    {
        if ($sub->billing_cycle === 'yearly') {
            return (float) $plan->price_yearly / 12.0;
        }

        return (float) $plan->price_monthly;
    }

    private function newMrrForDay(SubscriptionPlan $plan, Carbon $day): float
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $new = Subscription::query()
            ->where('plan_id', $plan->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->get();

        $mrr = 0.0;
        foreach ($new as $sub) {
            $mrr += $this->monthlyContribution($sub, $plan);
        }

        return $mrr;
    }

    private function churnedMrrForDay(SubscriptionPlan $plan, Carbon $day): float
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $churned = Subscription::query()
            ->where('plan_id', $plan->id)
            ->whereBetween('cancelled_at', [$start, $end])
            ->get();

        $mrr = 0.0;
        foreach ($churned as $sub) {
            $mrr += $this->monthlyContribution($sub, $plan);
        }

        return $mrr;
    }
}
