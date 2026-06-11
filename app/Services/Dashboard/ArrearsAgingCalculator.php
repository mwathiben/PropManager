<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Invoice;
use App\Traits\DatabaseAgnosticQueries;
use Illuminate\Support\Collection;

/**
 * Arrears-aging money math, extracted from DashboardService (M2
 * decomposition step 1). Pure read-side aggregation over outstanding
 * (overdue / partial) invoices. Behaviour is locked by
 * tests/Feature/Services/DashboardArrearsTest.php — this class was a
 * verbatim move, so those characterization tests prove parity.
 */
class ArrearsAgingCalculator
{
    use DatabaseAgnosticQueries;

    public function inRange(int $minDays, int $maxDays): float
    {
        // (float) cast: value('total') is a MySQL decimal STRING; under
        // strict_types a string can't satisfy the float return type.
        return (float) (Invoice::whereIn('status', ['overdue', 'partial'])
            ->where('due_date', '<=', now()->subDays($minDays))
            ->where('due_date', '>=', now()->subDays($maxDays))
            ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
            ->value('total') ?? 0);
    }

    public function inRangeForLeases(Collection $leaseIds, int $minDays, int $maxDays): float
    {
        if ($leaseIds->isEmpty()) {
            return 0.0;
        }

        return (float) (Invoice::whereIn('lease_id', $leaseIds)
            ->whereIn('status', ['overdue', 'partial'])
            ->where('due_date', '<=', now()->subDays($minDays))
            ->where('due_date', '>=', now()->subDays($maxDays))
            ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
            ->value('total') ?? 0);
    }

    /**
     * Compute all four arrears-aging buckets in ONE query instead of four
     * separate SUMs against the same row set.
     *
     * @return array{0_30: float, 31_60: float, 61_90: float, 90_plus: float}
     */
    public function agingBucketsForLeases(Collection $leaseIds): array
    {
        $empty = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];

        if ($leaseIds->isEmpty()) {
            return $empty;
        }

        $daysDiffSql = $this->getDaysBetweenSql('due_date', now()->format('Y-m-d'));

        $row = Invoice::whereIn('lease_id', $leaseIds)
            ->whereIn('status', ['overdue', 'partial'])
            ->whereNotNull('due_date')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN {$daysDiffSql} BETWEEN 0 AND 30
                    THEN total_due - amount_paid ELSE 0 END), 0) as bucket_0_30,
                COALESCE(SUM(CASE WHEN {$daysDiffSql} BETWEEN 31 AND 60
                    THEN total_due - amount_paid ELSE 0 END), 0) as bucket_31_60,
                COALESCE(SUM(CASE WHEN {$daysDiffSql} BETWEEN 61 AND 90
                    THEN total_due - amount_paid ELSE 0 END), 0) as bucket_61_90,
                COALESCE(SUM(CASE WHEN {$daysDiffSql} > 90
                    THEN total_due - amount_paid ELSE 0 END), 0) as bucket_90_plus
            ")
            ->first();

        return [
            '0_30' => round((float) $row->bucket_0_30, 2),
            '31_60' => round((float) $row->bucket_31_60, 2),
            '61_90' => round((float) $row->bucket_61_90, 2),
            '90_plus' => round((float) $row->bucket_90_plus, 2),
        ];
    }
}
