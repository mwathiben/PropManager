<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
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

        $payments = Payment::where('landlord_id', $landlordId)
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'completed')
            ->orderBy('payment_date')
            ->get();

        $trend = [];

        if ($groupBy === 'day') {
            $trend = $payments->groupBy(fn ($payment) => Carbon::parse($payment->payment_date)->format('Y-m-d'))
                ->map(fn ($group) => [
                    'date' => $group->first()->payment_date,
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                ])
                ->values()
                ->toArray();
        } elseif ($groupBy === 'week') {
            $trend = $payments->groupBy(fn ($payment) => Carbon::parse($payment->payment_date)->format('Y-W'))
                ->map(fn ($group) => [
                    'date' => 'Week '.Carbon::parse($group->first()->payment_date)->week,
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                ])
                ->values()
                ->toArray();
        } elseif ($groupBy === 'month') {
            $trend = $payments->groupBy(fn ($payment) => Carbon::parse($payment->payment_date)->format('Y-m'))
                ->map(fn ($group) => [
                    'date' => Carbon::parse($group->first()->payment_date)->format('M Y'),
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                ])
                ->values()
                ->toArray();
        }

        return $trend;
    }

    /**
     * Arrears analysis: aging, breakdown by unit
     */
    private function getArrearsAnalysis(int $landlordId): array
    {
        $overdueInvoices = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', ['overdue', 'partial'])
            ->with(['lease.unit', 'lease.tenant'])
            ->get();

        $now = Carbon::now();

        $aging = [
            '0-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '90+' => 0,
        ];

        $details = [];

        foreach ($overdueInvoices as $invoice) {
            $daysOverdue = $now->diffInDays(Carbon::parse($invoice->due_date));
            $outstanding = $invoice->total_due - $invoice->amount_paid;

            if ($daysOverdue <= 30) {
                $aging['0-30'] += $outstanding;
            } elseif ($daysOverdue <= 60) {
                $aging['31-60'] += $outstanding;
            } elseif ($daysOverdue <= 90) {
                $aging['61-90'] += $outstanding;
            } else {
                $aging['90+'] += $outstanding;
            }

            $details[] = [
                'unit' => $invoice->lease?->unit?->unit_number ?? 'N/A',
                'tenant' => $invoice->lease?->tenant?->name ?? 'N/A',
                'amount' => round($outstanding, 2),
                'days_overdue' => $daysOverdue,
                'invoice_number' => $invoice->invoice_number,
            ];
        }

        // Sort by days overdue descending
        usort($details, fn ($a, $b) => $b['days_overdue'] - $a['days_overdue']);

        return [
            'total_arrears' => round($overdueInvoices->sum(fn ($inv) => $inv->total_due - $inv->amount_paid), 2),
            'aging' => array_map(fn ($amount) => round($amount, 2), $aging),
            'count' => $overdueInvoices->count(),
            'details' => array_slice($details, 0, 10), // Top 10 worst offenders
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
            ->with(['activeLease'])
            ->get();

        $performance = [];

        foreach ($units as $unit) {
            if (! $unit->activeLease) {
                continue;
            }

            $invoices = Invoice::where('lease_id', $unit->activeLease->id)
                ->whereBetween('billing_period_start', [$dateRange['start'], $dateRange['end']])
                ->get();

            if ($invoices->isEmpty()) {
                continue;
            }

            $totalBilled = $invoices->sum('total_due');
            $totalPaid = $invoices->sum('amount_paid');
            $onTimePayments = $invoices->where('status', 'paid')->count();

            $performance[] = [
                'unit' => $unit->unit_number,
                'tenant' => $unit->activeLease->tenant?->name ?? 'N/A',
                'collection_rate' => $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100, 1) : 0,
                'on_time_payments' => $onTimePayments,
                'total_invoices' => $invoices->count(),
            ];
        }

        // Sort by collection rate descending
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
