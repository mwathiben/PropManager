<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\ProductEvent;
use App\Models\Subscription;
use App\Models\User;
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

    /**
     * Phase-56 COHORT-BY-SOURCE-2: partitioned retention by acquisition_source.
     *
     * For each (cohort_month, source) cell: cohort size = users in that
     * source bucket signed up in that month; retention[m] = users in that
     * cohort with at least one product_event recorded inside the boundary
     * month m calendar months after the cohort month.
     *
     * Activity-based (not subscription-based) so the curve answers
     * "are users still using the product?" rather than
     * "do they still have a paid subscription?".
     *
     * @param  list<int>|null  $restrictToUserIds  null = global cross-tenant aggregate; an id list scopes the cohort to exactly those users (used by the landlord-scoped retention variant).
     * @return array<int, array{cohort_month: string, source: string, size: int, retention: array<int, float>}>
     */
    public function cohortsBySource(int $monthsBack = 12, ?array $restrictToUserIds = null): array
    {
        $start = Carbon::now()->subMonthsNoOverflow($monthsBack)->startOfMonth();
        $now = Carbon::now()->startOfMonth();

        // Phase-57 READ-REPLICAS-3: heavy aggregate, eventual consistency OK.
        // Phase-66 COHORT-RETENTION-1: the optional id restriction lets the
        // landlord-scoped variant reuse this exact cohort SQL for just its
        // own referred users instead of duplicating it.
        $usersQuery = User::query()->withTrashed()->readOnly()
            ->where('created_at', '>=', $start);

        if ($restrictToUserIds !== null) {
            $usersQuery->whereIn('id', $restrictToUserIds);
        }

        $users = $usersQuery->get(['id', 'created_at', 'acquisition_source']);

        if ($users->isEmpty()) {
            return [];
        }

        $cohorts = [];
        foreach ($users as $user) {
            $cohortMonth = $user->created_at->copy()->startOfMonth()->format('Y-m');
            $source = $user->acquisition_source ?: 'unknown';
            $key = $cohortMonth.'|'.$source;
            $cohorts[$key] ??= [
                'cohort_month' => $cohortMonth,
                'source' => $source,
                'user_ids' => [],
            ];
            $cohorts[$key]['user_ids'][] = $user->id;
        }

        ksort($cohorts);

        $matrix = [];
        foreach ($cohorts as $cohort) {
            $cohortStart = Carbon::parse($cohort['cohort_month'].'-01')->startOfMonth();
            $monthsSinceCohort = $cohortStart->diffInMonths($now);
            $retention = [];
            for ($m = 0; $m <= $monthsSinceCohort; $m++) {
                $boundaryStart = $cohortStart->copy()->addMonthsNoOverflow($m)->startOfMonth();
                $boundaryEnd = $boundaryStart->copy()->endOfMonth();

                $activeCount = ProductEvent::query()->withoutGlobalScopes()
                    ->whereIn('user_id', $cohort['user_ids'])
                    ->whereBetween('created_at', [$boundaryStart, $boundaryEnd])
                    ->distinct('user_id')
                    ->count('user_id');

                $size = count($cohort['user_ids']);
                $retention[] = $size > 0 ? round($activeCount / $size, 4) : 0.0;
            }
            $matrix[] = [
                'cohort_month' => $cohort['cohort_month'],
                'source' => $cohort['source'],
                'size' => count($cohort['user_ids']),
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
