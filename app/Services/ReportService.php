<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\WaterReading;
use App\Traits\DatabaseAgnosticQueries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    use DatabaseAgnosticQueries;

    /**
     * Get comprehensive dashboard analytics for a landlord
     */
    public function getDashboardAnalytics(int $landlordId, ?string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);

        return [
            'financial' => $this->getFinancialMetrics($landlordId, $dateRange),
            'occupancy' => $this->getOccupancyMetrics($landlordId),
            'revenue_trend' => $this->getRevenueTrend($landlordId, $period),
            'arrears' => $this->getArrearsAnalysis($landlordId),
            'water_consumption' => $this->getWaterConsumptionAnalysis($landlordId, $dateRange),
            'collection_rate' => $this->getCollectionRate($landlordId, $dateRange),
            'top_performing_units' => $this->getTopPerformingUnits($landlordId, $dateRange),
            'period' => $period,
            'date_range' => $dateRange,
        ];
    }

    /**
     * Financial metrics: revenue, expenses, profit
     */
    private function getFinancialMetrics(int $landlordId, array $dateRange): array
    {
        // Total rent expected (from active leases)
        $expectedRent = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->sum('rent_amount');

        // Total rent collected (payments)
        $collectedRent = Payment::where('landlord_id', $landlordId)
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'completed')
            ->sum('amount');

        // Total water charges (from invoices)
        $waterCharges = Invoice::where('landlord_id', $landlordId)
            ->whereBetween('billing_period_start', [$dateRange['start'], $dateRange['end']])
            ->sum('water_due');

        // Total outstanding (unpaid invoices)
        $outstanding = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', ['sent', 'overdue', 'partial'])
            ->sum(DB::raw('total_due - amount_paid'));

        // Revenue by category
        $revenueBreakdown = [
            'rent' => $collectedRent,
            'water' => $waterCharges,
            'deposits' => Payment::where('landlord_id', $landlordId)
                ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
                ->where('payment_type', 'deposit')
                ->sum('amount'),
        ];

        return [
            'expected_rent' => round($expectedRent, 2),
            'collected_rent' => round($collectedRent, 2),
            'water_charges' => round($waterCharges, 2),
            'outstanding' => round($outstanding, 2),
            'total_revenue' => round($collectedRent + $waterCharges, 2),
            'revenue_breakdown' => $revenueBreakdown,
            'collection_percentage' => $expectedRent > 0 ? round(($collectedRent / $expectedRent) * 100, 1) : 0,
        ];
    }

    /**
     * Occupancy metrics: total units, occupied, vacant, rates
     */
    private function getOccupancyMetrics(int $landlordId): array
    {
        $units = Unit::where('landlord_id', $landlordId)->get();

        $totalUnits = $units->count();
        $occupied = $units->where('status', 'occupied')->count();
        $vacant = $units->where('status', 'vacant')->count();
        $maintenance = $units->where('status', 'maintenance')->count();
        $arrears = $units->where('status', 'arrears')->count();

        return [
            'total_units' => $totalUnits,
            'occupied' => $occupied,
            'vacant' => $vacant,
            'maintenance' => $maintenance,
            'arrears' => $arrears,
            'occupancy_rate' => $totalUnits > 0 ? round(($occupied / $totalUnits) * 100, 1) : 0,
            'vacancy_rate' => $totalUnits > 0 ? round(($vacant / $totalUnits) * 100, 1) : 0,
            'status_breakdown' => [
                'occupied' => $occupied,
                'vacant' => $vacant,
                'maintenance' => $maintenance,
                'arrears' => $arrears,
            ],
        ];
    }

    /**
     * Revenue trend over time (monthly for year, daily for month, weekly for quarter)
     */
    private function getRevenueTrend(int $landlordId, string $period): array
    {
        $dateRange = $this->getDateRange($period);
        $groupBy = match ($period) {
            'week' => 'day',
            'month' => 'day',
            'quarter' => 'week',
            'year' => 'month',
            default => 'day',
        };

        $dateFormat = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $dateFormatSql = $this->getDateFormatSql('payment_date', $dateFormat);

        $trendData = Payment::where('landlord_id', $landlordId)
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'completed')
            ->selectRaw("{$dateFormatSql} as date_group, MIN(payment_date) as first_date, SUM(amount) as total_amount, COUNT(*) as payment_count")
            ->groupBy('date_group')
            ->orderBy('date_group')
            ->get();

        return $trendData->map(function ($row) use ($groupBy) {
            $displayDate = match ($groupBy) {
                'day' => $row->first_date,
                'week' => 'Week '.Carbon::parse($row->first_date)->week,
                'month' => Carbon::parse($row->first_date)->format('M Y'),
                default => $row->first_date,
            };

            return [
                'date' => $displayDate,
                'amount' => round($row->total_amount, 2),
                'count' => (int) $row->payment_count,
            ];
        })->toArray();
    }

    /**
     * Arrears analysis: aging, breakdown by unit
     */
    private function getArrearsAnalysis(int $landlordId): array
    {
        $baseQuery = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', ['overdue', 'partial']);

        $dateDiff = $this->getDateDiffSql('due_date');

        $agingData = (clone $baseQuery)->selectRaw("
            COUNT(*) as total_count,
            SUM(total_due - amount_paid) as total_outstanding,
            SUM(CASE WHEN {$dateDiff} BETWEEN 0 AND 30 THEN total_due - amount_paid ELSE 0 END) as days_0_30,
            SUM(CASE WHEN {$dateDiff} BETWEEN 31 AND 60 THEN total_due - amount_paid ELSE 0 END) as days_31_60,
            SUM(CASE WHEN {$dateDiff} BETWEEN 61 AND 90 THEN total_due - amount_paid ELSE 0 END) as days_61_90,
            SUM(CASE WHEN {$dateDiff} > 90 THEN total_due - amount_paid ELSE 0 END) as days_90_plus
        ")->first();

        $aging = [
            '0-30' => round($agingData->days_0_30 ?? 0, 2),
            '31-60' => round($agingData->days_31_60 ?? 0, 2),
            '61-90' => round($agingData->days_61_90 ?? 0, 2),
            '90+' => round($agingData->days_90_plus ?? 0, 2),
        ];

        $details = (clone $baseQuery)
            ->with(['lease.unit:id,unit_number', 'lease.tenant:id,name'])
            ->select('id', 'lease_id', 'invoice_number', 'total_due', 'amount_paid', 'due_date')
            ->selectRaw("{$dateDiff} as days_overdue")
            ->orderByDesc('days_overdue')
            ->limit(10)
            ->get()
            ->map(fn ($invoice) => [
                'unit' => $invoice->lease?->unit?->unit_number ?? 'N/A',
                'tenant' => $invoice->lease?->tenant?->name ?? 'N/A',
                'amount' => round($invoice->total_due - $invoice->amount_paid, 2),
                'days_overdue' => max(0, (int) $invoice->days_overdue),
                'invoice_number' => $invoice->invoice_number,
            ])
            ->toArray();

        return [
            'total_arrears' => round($agingData->total_outstanding ?? 0, 2),
            'aging' => $aging,
            'count' => (int) ($agingData->total_count ?? 0),
            'details' => $details,
        ];
    }

    /**
     * Water consumption analysis
     */
    private function getWaterConsumptionAnalysis(int $landlordId, array $dateRange): array
    {
        $readings = WaterReading::where('landlord_id', $landlordId)
            ->whereBetween('reading_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'approved')
            ->get();

        $totalConsumption = $readings->sum('consumption');
        $totalCost = $readings->sum('cost');
        $avgConsumption = $readings->count() > 0 ? round($totalConsumption / $readings->count(), 2) : 0;

        // Top consumers
        $topConsumers = WaterReading::where('landlord_id', $landlordId)
            ->whereBetween('reading_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'approved')
            ->with('unit')
            ->select('unit_id', DB::raw('SUM(consumption) as total_consumption'), DB::raw('SUM(cost) as total_cost'))
            ->groupBy('unit_id')
            ->orderByDesc('total_consumption')
            ->limit(5)
            ->get()
            ->map(fn ($reading) => [
                'unit' => $reading->unit?->unit_number ?? 'N/A',
                'consumption' => round($reading->total_consumption, 2),
                'cost' => round($reading->total_cost, 2),
            ])
            ->toArray();

        return [
            'total_consumption' => round($totalConsumption, 2),
            'total_cost' => round($totalCost, 2),
            'average_consumption' => $avgConsumption,
            'readings_count' => $readings->count(),
            'top_consumers' => $topConsumers,
        ];
    }

    /**
     * Collection rate analysis
     */
    private function getCollectionRate(int $landlordId, array $dateRange): array
    {
        $invoices = Invoice::where('landlord_id', $landlordId)
            ->whereBetween('billing_period_start', [$dateRange['start'], $dateRange['end']])
            ->get();

        $totalBilled = $invoices->sum('total_due');
        $totalCollected = $invoices->sum('amount_paid');

        $paidInvoices = $invoices->where('status', 'paid')->count();
        $partialInvoices = $invoices->where('status', 'partial')->count();
        $overdueInvoices = $invoices->where('status', 'overdue')->count();

        return [
            'total_billed' => round($totalBilled, 2),
            'total_collected' => round($totalCollected, 2),
            'collection_rate' => $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0,
            'paid_count' => $paidInvoices,
            'partial_count' => $partialInvoices,
            'overdue_count' => $overdueInvoices,
            'total_invoices' => $invoices->count(),
        ];
    }

    /**
     * Top performing units (by payment consistency)
     */
    private function getTopPerformingUnits(int $landlordId, array $dateRange): array
    {
        $units = Unit::where('landlord_id', $landlordId)
            ->where('status', 'occupied')
            ->select('id', 'unit_number')
            ->with(['activeLease:id,unit_id,tenant_id', 'activeLease.tenant:id,name'])
            ->get()
            ->filter(fn ($u) => $u->activeLease !== null);

        if ($units->isEmpty()) {
            return [];
        }

        $leaseIds = $units->pluck('activeLease.id')->filter()->values();

        $invoiceStats = Invoice::whereIn('lease_id', $leaseIds)
            ->whereBetween('billing_period_start', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('lease_id, SUM(total_due) as total_billed, SUM(amount_paid) as total_paid, COUNT(*) as invoice_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as on_time_count', ['paid'])
            ->groupBy('lease_id')
            ->get()
            ->keyBy('lease_id');

        $performance = [];

        foreach ($units as $unit) {
            $stats = $invoiceStats->get($unit->activeLease->id);

            if (! $stats || $stats->invoice_count == 0) {
                continue;
            }

            $performance[] = [
                'unit' => $unit->unit_number,
                'tenant' => $unit->activeLease->tenant?->name ?? 'N/A',
                'collection_rate' => $stats->total_billed > 0 ? round(($stats->total_paid / $stats->total_billed) * 100, 1) : 0,
                'on_time_payments' => (int) $stats->on_time_count,
                'total_invoices' => (int) $stats->invoice_count,
            ];
        }

        usort($performance, fn ($a, $b) => $b['collection_rate'] <=> $a['collection_rate']);

        return array_slice($performance, 0, 5);
    }

    /**
     * Get date range based on period
     */
    public function getDateRange(string $period): array
    {
        $end = Carbon::now();

        $start = match ($period) {
            'week' => Carbon::now()->subDays(7),
            'month' => Carbon::now()->subMonth(),
            'quarter' => Carbon::now()->subMonths(3),
            'year' => Carbon::now()->subYear(),
            default => Carbon::now()->subMonth(),
        };

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    /**
     * Export report data to array format (for PDF/Excel)
     */
    public function exportData(int $landlordId, string $reportType, ?string $period = 'month'): array
    {
        $analytics = $this->getDashboardAnalytics($landlordId, $period);

        return match ($reportType) {
            'financial' => $this->formatFinancialExport($analytics),
            'occupancy' => $this->formatOccupancyExport($analytics),
            'arrears' => $this->formatArrearsExport($analytics),
            'water' => $this->formatWaterExport($analytics),
            default => $analytics,
        };
    }

    private function formatFinancialExport(array $analytics): array
    {
        return [
            'title' => 'Financial Report',
            'period' => $analytics['period'],
            'date_range' => $analytics['date_range'],
            'summary' => $analytics['financial'],
            'revenue_trend' => $analytics['revenue_trend'],
            'collection_rate' => $analytics['collection_rate'],
        ];
    }

    private function formatOccupancyExport(array $analytics): array
    {
        return [
            'title' => 'Occupancy Report',
            'summary' => $analytics['occupancy'],
            'top_performers' => $analytics['top_performing_units'],
        ];
    }

    private function formatArrearsExport(array $analytics): array
    {
        return [
            'title' => 'Arrears Analysis Report',
            'summary' => $analytics['arrears'],
            'aging_breakdown' => $analytics['arrears']['aging'],
            'details' => $analytics['arrears']['details'],
        ];
    }

    private function formatWaterExport(array $analytics): array
    {
        return [
            'title' => 'Water Consumption Report',
            'period' => $analytics['period'],
            'summary' => $analytics['water_consumption'],
            'top_consumers' => $analytics['water_consumption']['top_consumers'],
        ];
    }
}
