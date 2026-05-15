<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Lease;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase-27 BI-COHORT-1/2/3: tenant cohort analytics.
 *
 * Three views of the same underlying data (leases grouped by start_date
 * month):
 *   1. retentionMatrix — triangular survival rate by cohort × offset
 *      month. Diagonal is always 100% (a cohort survives its own start
 *      month). Below-diagonal cells are NULL (no data — the future).
 *   2. acquisitionTable — absolute counts of new / churned / reactivated
 *      leases per month, with net delta. Reconciles to active lease
 *      count at the end of the window.
 *   3. lifetimeValue — sum of tenant payments per acquisition cohort,
 *      mean + median across tenants in the cohort. The methodology
 *      (which payments count, refund handling, lease-renewal semantics)
 *      is documented in docs/runbooks/bi.md.
 *
 * "Cohort" = leases that STARTED in a given YYYY-MM. A tenant whose
 * first lease ended and who started a new lease in the same building
 * counts as a NEW cohort member at the second start_date — the
 * acquisitionTable surfaces this as a `reactivated` count so it isn't
 * inflated into `new`.
 */
class CohortService
{
    /**
     * Survival matrix indexed by cohort month → offset months from cohort start.
     *
     * matrix[YYYY-MM][offset] = float (survival percentage 0.0–1.0)
     *
     * Diagonal (offset=0) is always 1.0. Cells in the future are null
     * (no observation possible). Cohorts with zero starts are omitted.
     *
     * @return array<string, array<int, float|null>>
     */
    public function retentionMatrix(int $landlordId, int $lookbackMonths = 12): array
    {
        $start = Carbon::now()->subMonths($lookbackMonths - 1)->startOfMonth();
        $end = Carbon::now()->startOfMonth();

        $leases = Lease::query()
            ->where('landlord_id', $landlordId)
            ->whereBetween('start_date', [$start, Carbon::now()->endOfMonth()])
            ->select('id', 'tenant_id', 'start_date', 'end_date', 'is_active')
            ->get();

        // Group leases by cohort month (YYYY-MM of start_date).
        $cohorts = $leases->groupBy(fn ($lease) => $lease->start_date->format('Y-m'));

        $matrix = [];
        foreach ($cohorts as $cohortMonth => $cohortLeases) {
            $cohortStart = Carbon::parse($cohortMonth.'-01');
            $size = $cohortLeases->count();
            if ($size === 0) {
                continue;
            }

            for ($offset = 0; $offset <= $lookbackMonths; $offset++) {
                $observationDate = $cohortStart->copy()->addMonths($offset)->endOfMonth();

                // Observation is in the future — leave NULL.
                if ($observationDate->isFuture()) {
                    $matrix[$cohortMonth][$offset] = null;

                    continue;
                }

                $alive = $cohortLeases->filter(function ($lease) use ($observationDate) {
                    // end_date is a date cast (00:00:00). Compare at
                    // end-of-day so a lease ending ON the observation
                    // date is still considered alive through that day.
                    $endsAfter = $lease->end_date === null
                        || $lease->end_date->copy()->endOfDay()->greaterThanOrEqualTo($observationDate);

                    return $lease->start_date->lessThanOrEqualTo($observationDate) && $endsAfter;
                })->count();

                $matrix[$cohortMonth][$offset] = round($alive / $size, 4);
            }
        }

        return $matrix;
    }

    /**
     * Monthly acquisition deltas. Each row is one calendar month.
     *
     * new           — leases that started in this month AND tenant has
     *                 no prior lease anywhere with the landlord
     * reactivated   — leases that started in this month AND tenant
     *                 had a prior lease that already ended
     * churned       — leases whose end_date falls in this month
     * net_delta     — new + reactivated − churned
     *
     * @return list<array{month: string, new: int, reactivated: int, churned: int, net_delta: int}>
     */
    public function acquisitionTable(int $landlordId, int $months = 12): array
    {
        $end = Carbon::now()->endOfMonth();
        $start = $end->copy()->subMonths($months - 1)->startOfMonth();

        $leases = Lease::query()
            ->where('landlord_id', $landlordId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end]);
            })
            ->select('id', 'tenant_id', 'start_date', 'end_date')
            ->get();

        // For 'reactivated': we need to know the FIRST lease per tenant.
        // Anything starting after that tenant's first end_date counts
        // as reactivated, not new.
        $firstLeasePerTenant = Lease::query()
            ->where('landlord_id', $landlordId)
            ->select('tenant_id', DB::raw('MIN(start_date) as first_start'))
            ->groupBy('tenant_id')
            ->pluck('first_start', 'tenant_id');

        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthKey = $monthStart->format('Y-m');
            $monthEnd = $monthStart->copy()->endOfMonth();

            $startedThisMonth = $leases->filter(
                fn ($l) => $l->start_date->betweenIncluded($monthStart, $monthEnd),
            );

            $new = 0;
            $reactivated = 0;
            foreach ($startedThisMonth as $lease) {
                $firstStart = $firstLeasePerTenant->get($lease->tenant_id);
                if ($firstStart === null) {
                    $new++;

                    continue;
                }
                $firstStartCarbon = $firstStart instanceof \Carbon\CarbonInterface
                    ? $firstStart
                    : Carbon::parse((string) $firstStart);
                if ($firstStartCarbon->equalTo($lease->start_date)) {
                    $new++;
                } else {
                    $reactivated++;
                }
            }

            $churned = $leases->filter(
                fn ($l) => $l->end_date !== null && $l->end_date->betweenIncluded($monthStart, $monthEnd),
            )->count();

            $rows[] = [
                'month' => $monthKey,
                'new' => $new,
                'reactivated' => $reactivated,
                'churned' => $churned,
                'net_delta' => $new + $reactivated - $churned,
            ];
        }

        return $rows;
    }

    /**
     * Lifetime value per acquisition cohort.
     *
     * For each cohort month, returns the count of tenants and the sum
     * of every payment they've made (excluding refunds). The methodology
     * is documented in docs/runbooks/bi.md — single-formula LTV does
     * not exist, this is PropManager's chosen interpretation.
     *
     * @return array{tenants_count: int, total_payments: float, mean_ltv: float, median_ltv: float}
     */
    public function lifetimeValue(int $landlordId, string $cohortMonth): array
    {
        $cohortStart = Carbon::parse($cohortMonth.'-01')->startOfMonth();
        $cohortEnd = $cohortStart->copy()->endOfMonth();

        $tenantIds = Lease::query()
            ->where('landlord_id', $landlordId)
            ->whereBetween('start_date', [$cohortStart, $cohortEnd])
            ->pluck('tenant_id')
            ->unique()
            ->values();

        if ($tenantIds->isEmpty()) {
            return [
                'tenants_count' => 0,
                'total_payments' => 0.0,
                'mean_ltv' => 0.0,
                'median_ltv' => 0.0,
            ];
        }

        // Sum payments per tenant. Payments link to tenants through
        // leases (payments.lease_id → leases.tenant_id). Voided
        // payments (is_voided=true) are excluded — they represent
        // reversed transactions. Refunds (separate Refund model) are
        // a Phase-N follow-up; the methodology doc flags this as a
        // known limitation: the current LTV is gross-of-refunds.
        $perTenant = Payment::query()
            ->join('leases', 'leases.id', '=', 'payments.lease_id')
            ->where('payments.landlord_id', $landlordId)
            ->whereIn('leases.tenant_id', $tenantIds)
            ->where(function ($q) {
                $q->whereNull('payments.is_voided')->orWhere('payments.is_voided', false);
            })
            ->select('leases.tenant_id as tenant_id', DB::raw('SUM(payments.amount) as total'))
            ->groupBy('leases.tenant_id')
            ->pluck('total', 'tenant_id');

        // Tenants in the cohort with NO payments yet should still
        // count toward the cohort size (depresses mean LTV honestly).
        $perTenantValues = $tenantIds
            ->map(fn ($id) => (float) ($perTenant->get($id) ?? 0))
            ->sort()
            ->values();

        $count = $perTenantValues->count();
        $total = $perTenantValues->sum();
        $mean = $count > 0 ? $total / $count : 0.0;

        $median = 0.0;
        if ($count > 0) {
            $middle = (int) floor(($count - 1) / 2);
            if ($count % 2 === 1) {
                $median = $perTenantValues[$middle];
            } else {
                $median = ($perTenantValues[$middle] + $perTenantValues[$middle + 1]) / 2;
            }
        }

        return [
            'tenants_count' => $count,
            'total_payments' => round($total, 2),
            'mean_ltv' => round($mean, 2),
            'median_ltv' => round($median, 2),
        ];
    }
}
