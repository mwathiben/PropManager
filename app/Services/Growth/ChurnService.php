<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Phase-34 GROWTH-CHURN-2: SaaS subscription cohort retention.
 *
 * Shape mirrors Phase-27 CohortService::retentionMatrix (triangular,
 * cohorts as rows + months-since-signup as columns) — but keyed on
 * the global subscriptions stream, not on per-landlord leases. Two
 * services co-exist because the CohortService one answers "how
 * sticky is THIS landlord's tenant base" (a per-landlord retention
 * metric) while this one answers "how sticky is our SaaS itself"
 * (a company-level metric).
 *
 * Retention[m] for cohort C = subscriptions still active m months
 * after the cohort month / total subscriptions in cohort C.
 */
class ChurnService
{
    public function subscriptionCohorts(int $monthsBack = 12): array
    {
        $start = Carbon::now()->subMonthsNoOverflow($monthsBack)->startOfMonth();
        $now = Carbon::now()->startOfMonth();

        $subs = Subscription::query()
            ->where('created_at', '>=', $start)
            ->get(['id', 'created_at', 'cancelled_at']);

        $cohorts = [];
        foreach ($subs as $sub) {
            $cohortMonth = $sub->created_at->copy()->startOfMonth();
            $key = $cohortMonth->format('Y-m');
            $cohorts[$key] ??= ['cohort_month' => $key, 'size' => 0, 'subs' => []];
            $cohorts[$key]['size']++;
            $cohorts[$key]['subs'][] = $sub;
        }

        ksort($cohorts);

        $matrix = [];
        foreach ($cohorts as $key => $cohort) {
            $cohortMonth = Carbon::parse($key.'-01');
            $monthsSinceCohort = $cohortMonth->diffInMonths($now);
            $retention = [];
            for ($m = 0; $m <= $monthsSinceCohort; $m++) {
                $boundary = $cohortMonth->copy()->addMonthsNoOverflow($m)->endOfMonth();
                $stillActive = 0;
                foreach ($cohort['subs'] as $sub) {
                    if ($sub->cancelled_at === null || $sub->cancelled_at->greaterThan($boundary)) {
                        $stillActive++;
                    }
                }
                $retention[] = $cohort['size'] > 0
                    ? round($stillActive / $cohort['size'], 4)
                    : 0.0;
            }
            $matrix[] = [
                'cohort_month' => $key,
                'size' => $cohort['size'],
                'retention' => $retention,
            ];
        }

        return $matrix;
    }

    public function monthlyChurnRate(?Carbon $month = null): float
    {
        $month = $month ? $month->copy()->startOfMonth() : Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $activeAtStart = Subscription::query()
            ->where('created_at', '<', $month)
            ->where(function ($q) use ($month) {
                $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $month);
            })
            ->count();

        if ($activeAtStart === 0) {
            return 0.0;
        }

        $churnedInMonth = Subscription::query()
            ->whereBetween('cancelled_at', [$month, $monthEnd])
            ->count();

        return round($churnedInMonth / $activeAtStart, 4);
    }
}
