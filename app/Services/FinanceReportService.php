<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use App\Traits\DatabaseAgnosticQueries;
use Carbon\Carbon;

class FinanceReportService
{
    use DatabaseAgnosticQueries;

    private function buildCacheFilters(array $dateRange, ?int $buildingId = null): array
    {
        return [
            'start' => $dateRange['start']->format('Y-m-d'),
            'end' => $dateRange['end']->format('Y-m-d'),
            'building_id' => $buildingId,
        ];
    }

    public function getRevenueReport(int $landlordId, int $months, ?int $buildingId = null): array
    {
        $data = [];
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $invoiceQuery = Invoice::where('landlord_id', $landlordId)
                ->whereBetween('created_at', [$monthStart, $monthEnd]);
            $paymentQuery = Payment::withArchived()->where('landlord_id', $landlordId)
                ->where('is_voided', false)
                ->whereBetween('payment_date', [$monthStart, $monthEnd]);
            $expenseQuery = Expense::where('landlord_id', $landlordId)
                ->whereBetween('expense_date', [$monthStart, $monthEnd]);

            if ($buildingId) {
                $invoiceQuery->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
                $paymentQuery->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
                $expenseQuery->where('building_id', $buildingId);
            }

            $invoiced = $invoiceQuery->sum('total_due');
            $collected = $paymentQuery->sum('amount');
            $expenses = $expenseQuery->sum('amount');

            $data[] = [
                'month' => $monthStart->format('M Y'),
                'invoiced' => round($invoiced, 2),
                'collected' => round($collected, 2),
                'expenses' => round($expenses, 2),
                'net' => round($collected - $expenses, 2),
            ];
        }

        return $data;
    }

    public function getRevenueReportFiltered(int $landlordId, array $dateRange, ?int $buildingId = null): array
    {
        $data = [];
        $months = max(1, $dateRange['start']->diffInMonths($dateRange['end']) + 1);
        $startDate = $dateRange['start']->copy()->startOfMonth();

        for ($i = 0; $i < $months && $i < 12; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            if ($monthEnd->gt($dateRange['end'])) {
                $monthEnd = $dateRange['end']->copy();
            }

            $invoiceQuery = Invoice::where('landlord_id', $landlordId)
                ->whereBetween('created_at', [$monthStart, $monthEnd]);
            $paymentQuery = Payment::withArchived()->where('landlord_id', $landlordId)
                ->where('is_voided', false)
                ->whereBetween('payment_date', [$monthStart, $monthEnd]);
            $expenseQuery = Expense::where('landlord_id', $landlordId)
                ->whereBetween('expense_date', [$monthStart, $monthEnd]);

            if ($buildingId) {
                $invoiceQuery->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
                $paymentQuery->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
                $expenseQuery->where('building_id', $buildingId);
            }

            $invoiced = $invoiceQuery->sum('total_due');
            $collected = $paymentQuery->sum('amount');
            $expenses = $expenseQuery->sum('amount');

            $data[] = [
                'month' => $monthStart->format('M Y'),
                'invoiced' => round($invoiced, 2),
                'collected' => round($collected, 2),
                'expenses' => round($expenses, 2),
                'net' => round($collected - $expenses, 2),
            ];
        }

        return $data;
    }

    public function getCollectionRateReport(int $landlordId, int $months, ?int $buildingId = null): array
    {
        $data = [];
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $invoiceQuery = Invoice::where('landlord_id', $landlordId)
                ->whereBetween('due_date', [$monthStart, $monthEnd]);

            if ($buildingId) {
                $invoiceQuery->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
            }

            $invoiced = $invoiceQuery->sum('total_due');
            $collected = $invoiceQuery->sum('amount_paid');
            $rate = $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0;

            $data[] = [
                'month' => $monthStart->format('M Y'),
                'invoiced' => round($invoiced, 2),
                'collected' => round($collected, 2),
                'rate' => $rate,
            ];
        }

        return $data;
    }

    public function getCollectionRateReportFiltered(int $landlordId, array $dateRange, ?int $buildingId = null): array
    {
        $data = [];
        $months = max(1, $dateRange['start']->diffInMonths($dateRange['end']) + 1);
        $startDate = $dateRange['start']->copy()->startOfMonth();

        for ($i = 0; $i < $months && $i < 12; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            if ($monthEnd->gt($dateRange['end'])) {
                $monthEnd = $dateRange['end']->copy();
            }

            $invoiceQuery = Invoice::where('landlord_id', $landlordId)
                ->whereBetween('due_date', [$monthStart, $monthEnd]);

            if ($buildingId) {
                $invoiceQuery->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
            }

            $invoiced = $invoiceQuery->sum('total_due');
            $collected = $invoiceQuery->sum('amount_paid');
            $rate = $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0;

            $data[] = [
                'month' => $monthStart->format('M Y'),
                'rate' => $rate,
            ];
        }

        return $data;
    }

    public function getOccupancyReport(int $landlordId, ?int $buildingId = null): array
    {
        return FinanceCacheService::rememberReport('occupancy', $landlordId, ['building_id' => $buildingId], function () use ($landlordId, $buildingId) {
            $query = Building::where('landlord_id', $landlordId)
                ->with(['units' => fn ($q) => $q->withCount(['leases' => fn ($l) => $l->where('is_active', true)])]);

            if ($buildingId) {
                $query->where('id', $buildingId);
            }

            $buildings = $query->get();
            $data = [];
            $totalUnits = 0;
            $totalOccupied = 0;

            foreach ($buildings as $building) {
                $units = $building->units->count();
                $occupied = $building->units->where('leases_count', '>', 0)->count();
                $vacant = $units - $occupied;
                $rate = $units > 0 ? round(($occupied / $units) * 100, 1) : 0;

                $totalUnits += $units;
                $totalOccupied += $occupied;

                $data[] = [
                    'building' => $building->name,
                    'total_units' => $units,
                    'occupied' => $occupied,
                    'vacant' => $vacant,
                    'occupancy_rate' => $rate,
                ];
            }

            return [
                'buildings' => $data,
                'totals' => [
                    'building' => 'Total',
                    'total_units' => $totalUnits,
                    'occupied' => $totalOccupied,
                    'vacant' => $totalUnits - $totalOccupied,
                    'occupancy_rate' => $totalUnits > 0 ? round(($totalOccupied / $totalUnits) * 100, 1) : 0,
                ],
            ];
        });
    }

    public function getArrearsAgingReport(int $landlordId, ?int $buildingId = null): array
    {
        return FinanceCacheService::rememberReport('arrears_aging', $landlordId, ['building_id' => $buildingId], function () use ($landlordId, $buildingId) {
            $query = Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->whereRaw('total_due > amount_paid');

            if ($buildingId) {
                $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
            }

            $dateDiff = $this->getDateDiffSql('due_date');

            $agingData = (clone $query)->selectRaw("
                COUNT(*) as total_count,
                SUM(total_due - amount_paid) as total_outstanding,
                SUM(CASE WHEN due_date IS NULL OR {$dateDiff} <= 0 THEN 1 ELSE 0 END) as current_count,
                SUM(CASE WHEN due_date IS NULL OR {$dateDiff} <= 0 THEN total_due - amount_paid ELSE 0 END) as current_amount,
                SUM(CASE WHEN {$dateDiff} BETWEEN 1 AND 30 THEN 1 ELSE 0 END) as days_1_30_count,
                SUM(CASE WHEN {$dateDiff} BETWEEN 1 AND 30 THEN total_due - amount_paid ELSE 0 END) as days_1_30_amount,
                SUM(CASE WHEN {$dateDiff} BETWEEN 31 AND 60 THEN 1 ELSE 0 END) as days_31_60_count,
                SUM(CASE WHEN {$dateDiff} BETWEEN 31 AND 60 THEN total_due - amount_paid ELSE 0 END) as days_31_60_amount,
                SUM(CASE WHEN {$dateDiff} BETWEEN 61 AND 90 THEN 1 ELSE 0 END) as days_61_90_count,
                SUM(CASE WHEN {$dateDiff} BETWEEN 61 AND 90 THEN total_due - amount_paid ELSE 0 END) as days_61_90_amount,
                SUM(CASE WHEN {$dateDiff} > 90 THEN 1 ELSE 0 END) as days_90_plus_count,
                SUM(CASE WHEN {$dateDiff} > 90 THEN total_due - amount_paid ELSE 0 END) as days_90_plus_amount
            ")->first();

            $aging = [
                'current' => ['count' => (int) ($agingData->current_count ?? 0), 'amount' => round($agingData->current_amount ?? 0, 2)],
                '1-30' => ['count' => (int) ($agingData->days_1_30_count ?? 0), 'amount' => round($agingData->days_1_30_amount ?? 0, 2)],
                '31-60' => ['count' => (int) ($agingData->days_31_60_count ?? 0), 'amount' => round($agingData->days_31_60_amount ?? 0, 2)],
                '61-90' => ['count' => (int) ($agingData->days_61_90_count ?? 0), 'amount' => round($agingData->days_61_90_amount ?? 0, 2)],
                '90+' => ['count' => (int) ($agingData->days_90_plus_count ?? 0), 'amount' => round($agingData->days_90_plus_amount ?? 0, 2)],
            ];

            return [
                'aging' => $aging,
                'total_outstanding' => round($agingData->total_outstanding ?? 0, 2),
                'total_invoices' => (int) ($agingData->total_count ?? 0),
            ];
        });
    }

    public function getExpensesByCategoryReport(int $landlordId, int $months, ?int $buildingId = null): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        $query = Expense::withoutGlobalScope('landlord')
            ->where('expenses.landlord_id', $landlordId)
            ->where('expenses.expense_date', '>=', $startDate)
            ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->selectRaw('expenses.category_id, expense_categories.name as category_name, expense_categories.color as category_color, SUM(expenses.amount) as total_amount, COUNT(*) as expense_count')
            ->groupBy('expenses.category_id', 'expense_categories.name', 'expense_categories.color');

        if ($buildingId) {
            $query->where('expenses.building_id', $buildingId);
        }

        $categoryData = $query->get();
        $grandTotal = $categoryData->sum('total_amount');

        $byCategory = $categoryData->map(fn ($row) => [
            'category' => $row->category_name ?? 'Uncategorized',
            'color' => $row->category_color ?? '#6B7280',
            'amount' => round($row->total_amount, 2),
            'count' => (int) $row->expense_count,
            'percentage' => $grandTotal > 0 ? round(($row->total_amount / $grandTotal) * 100, 1) : 0,
        ])->values();

        return [
            'categories' => $byCategory->toArray(),
            'total' => round($grandTotal, 2),
        ];
    }

    public function getExpensesByCategoryReportFiltered(int $landlordId, array $dateRange, ?int $buildingId = null): array
    {
        $query = Expense::withoutGlobalScope('landlord')
            ->where('expenses.landlord_id', $landlordId)
            ->whereBetween('expenses.expense_date', [$dateRange['start'], $dateRange['end']])
            ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->selectRaw('expenses.category_id, expense_categories.name as category_name, expense_categories.color as category_color, SUM(expenses.amount) as total_amount, COUNT(*) as expense_count')
            ->groupBy('expenses.category_id', 'expense_categories.name', 'expense_categories.color');

        if ($buildingId) {
            $query->where('expenses.building_id', $buildingId);
        }

        $categoryData = $query->get();
        $grandTotal = $categoryData->sum('total_amount');

        $byCategory = $categoryData->map(fn ($row) => [
            'category' => $row->category_name ?? 'Uncategorized',
            'color' => $row->category_color ?? '#6B7280',
            'amount' => round($row->total_amount, 2),
            'count' => (int) $row->expense_count,
            'percentage' => $grandTotal > 0 ? round(($row->total_amount / $grandTotal) * 100, 1) : 0,
        ])->values();

        return [
            'categories' => $byCategory->toArray(),
            'total' => round($grandTotal, 2),
        ];
    }

    public function getWaterConsumptionReport(int $landlordId, int $months, ?int $buildingId = null): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        $baseQuery = WaterReading::where('landlord_id', $landlordId)
            ->where('reading_date', '>=', $startDate)
            ->where('status', 'approved');

        if ($buildingId) {
            $baseQuery->whereHas('unit', fn ($q) => $q->where('building_id', $buildingId));
        }

        $aggregates = (clone $baseQuery)->selectRaw('SUM(consumption) as total_consumption, SUM(cost) as total_cost, COUNT(*) as readings_count')->first();

        $totalConsumption = $aggregates->total_consumption ?? 0;
        $totalCost = $aggregates->total_cost ?? 0;
        $readingsCount = (int) ($aggregates->readings_count ?? 0);
        $avgConsumption = $readingsCount > 0 ? round($totalConsumption / $readingsCount, 2) : 0;

        $topQuery = WaterReading::where('landlord_id', $landlordId)
            ->where('reading_date', '>=', $startDate)
            ->where('status', 'approved')
            ->with('unit:id,unit_number,building_id', 'unit.building:id,name')
            ->selectRaw('unit_id, SUM(consumption) as total_consumption, SUM(cost) as total_cost')
            ->groupBy('unit_id')
            ->orderByDesc('total_consumption')
            ->limit(10);

        if ($buildingId) {
            $topQuery->whereHas('unit', fn ($q) => $q->where('building_id', $buildingId));
        }

        $topConsumers = $topQuery->get()
            ->map(fn ($reading) => [
                'unit' => $reading->unit?->unit_number ?? 'N/A',
                'building' => $reading->unit?->building?->name ?? 'N/A',
                'consumption' => round($reading->total_consumption, 2),
                'cost' => round($reading->total_cost, 2),
            ])
            ->toArray();

        return [
            'total_consumption' => round($totalConsumption, 2),
            'total_cost' => round($totalCost, 2),
            'average_consumption' => $avgConsumption,
            'readings_count' => $readingsCount,
            'top_consumers' => $topConsumers,
        ];
    }

    public function getWaterConsumptionReportFiltered(int $landlordId, array $dateRange, ?int $buildingId = null): array
    {
        $baseQuery = WaterReading::where('landlord_id', $landlordId)
            ->whereBetween('reading_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'approved');

        if ($buildingId) {
            $baseQuery->whereHas('unit', fn ($q) => $q->where('building_id', $buildingId));
        }

        $aggregates = (clone $baseQuery)->selectRaw('SUM(consumption) as total_consumption, SUM(cost) as total_cost, COUNT(*) as readings_count')->first();

        $totalConsumption = $aggregates->total_consumption ?? 0;
        $totalCost = $aggregates->total_cost ?? 0;
        $readingsCount = (int) ($aggregates->readings_count ?? 0);
        $avgConsumption = $readingsCount > 0 ? round($totalConsumption / $readingsCount, 2) : 0;

        $topQuery = WaterReading::where('landlord_id', $landlordId)
            ->whereBetween('reading_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'approved')
            ->with('unit:id,unit_number,building_id', 'unit.building:id,name')
            ->selectRaw('unit_id, SUM(consumption) as total_consumption, SUM(cost) as total_cost')
            ->groupBy('unit_id')
            ->orderByDesc('total_consumption')
            ->limit(10);

        if ($buildingId) {
            $topQuery->whereHas('unit', fn ($q) => $q->where('building_id', $buildingId));
        }

        $topConsumers = $topQuery->get()
            ->map(fn ($reading) => [
                'unit' => $reading->unit?->unit_number ?? 'N/A',
                'building' => $reading->unit?->building?->name ?? 'N/A',
                'consumption' => round($reading->total_consumption, 2),
                'cost' => round($reading->total_cost, 2),
            ])
            ->toArray();

        return [
            'total_consumption' => round($totalConsumption, 2),
            'total_cost' => round($totalCost, 2),
            'average_consumption' => $avgConsumption,
            'readings_count' => $readingsCount,
            'top_consumers' => $topConsumers,
        ];
    }

    public function getTopPerformingUnitsReport(int $landlordId, int $months, ?int $buildingId = null): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        $query = Unit::where('landlord_id', $landlordId)
            ->where('status', 'occupied')
            ->with(['activeLease.tenant:id,name', 'building:id,name']);

        if ($buildingId) {
            $query->where('building_id', $buildingId);
        }

        $units = $query->get()->filter(fn ($u) => $u->activeLease !== null);

        if ($units->isEmpty()) {
            return [];
        }

        $leaseIds = $units->pluck('activeLease.id')->filter()->values();

        $invoiceStats = Invoice::whereIn('lease_id', $leaseIds)
            ->where('created_at', '>=', $startDate)
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
                'building' => $unit->building?->name ?? 'N/A',
                'tenant' => $unit->activeLease->tenant?->name ?? 'N/A',
                'collection_rate' => $stats->total_billed > 0 ? round(($stats->total_paid / $stats->total_billed) * 100, 1) : 0,
                'on_time_payments' => (int) $stats->on_time_count,
                'total_invoices' => (int) $stats->invoice_count,
            ];
        }

        usort($performance, fn ($a, $b) => $b['collection_rate'] <=> $a['collection_rate']);

        return array_slice($performance, 0, 10);
    }

    public function getTopPerformingUnitsReportFiltered(int $landlordId, array $dateRange, ?int $buildingId = null): array
    {
        $query = Unit::where('landlord_id', $landlordId)
            ->where('status', 'occupied')
            ->with(['activeLease.tenant:id,name', 'building:id,name']);

        if ($buildingId) {
            $query->where('building_id', $buildingId);
        }

        $units = $query->get()->filter(fn ($u) => $u->activeLease !== null);

        if ($units->isEmpty()) {
            return [];
        }

        $leaseIds = $units->pluck('activeLease.id')->filter()->values();

        $invoiceStats = Invoice::whereIn('lease_id', $leaseIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
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
                'building' => $unit->building?->name ?? 'N/A',
                'tenant' => $unit->activeLease->tenant?->name ?? 'N/A',
                'collection_rate' => $stats->total_billed > 0 ? round(($stats->total_paid / $stats->total_billed) * 100, 1) : 0,
                'on_time_payments' => (int) $stats->on_time_count,
                'total_invoices' => (int) $stats->invoice_count,
            ];
        }

        usort($performance, fn ($a, $b) => $b['collection_rate'] <=> $a['collection_rate']);

        return array_slice($performance, 0, 10);
    }

    public function getReportDateRange(string $period, ?string $dateFrom, ?string $dateTo, ?int $landlordId = null): array
    {
        if ($period === 'custom' && $dateFrom && $dateTo) {
            return [
                'start' => Carbon::parse($dateFrom)->startOfDay(),
                'end' => Carbon::parse($dateTo)->endOfDay(),
            ];
        }

        $now = now();

        $fiscalYearStart = null;
        $fiscalYearEnd = null;
        $previousFiscalYearStart = null;
        $previousFiscalYearEnd = null;

        if (in_array($period, ['ytd', 'this_fy', 'last_fy']) && $landlordId) {
            $user = User::find($landlordId);
            $settings = $user?->invoiceSetting;

            if ($settings && $settings->fiscal_year_type === 'custom') {
                $fiscalYearStart = $settings->getFiscalYearStart($now);
                $fiscalYearEnd = $settings->getFiscalYearEnd($now);
                $previousFiscalYearStart = $settings->getPreviousFiscalYearStart($now);
                $previousFiscalYearEnd = $settings->getPreviousFiscalYearEnd($now);
            } else {
                $fiscalYearStart = $now->copy()->startOfYear();
                $fiscalYearEnd = $now->copy()->endOfYear();
                $previousFiscalYearStart = $now->copy()->subYear()->startOfYear();
                $previousFiscalYearEnd = $now->copy()->subYear()->endOfYear();
            }
        }

        return match ($period) {
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'last_month' => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth(),
            ],
            'this_quarter' => [
                'start' => $now->copy()->firstOfQuarter(),
                'end' => $now->copy()->lastOfQuarter(),
            ],
            'last_quarter' => [
                'start' => $now->copy()->subQuarter()->firstOfQuarter(),
                'end' => $now->copy()->subQuarter()->lastOfQuarter(),
            ],
            'ytd' => [
                'start' => $fiscalYearStart,
                'end' => $now->copy()->endOfDay(),
            ],
            'this_fy' => [
                'start' => $fiscalYearStart,
                'end' => $fiscalYearEnd,
            ],
            'last_fy' => [
                'start' => $previousFiscalYearStart,
                'end' => $previousFiscalYearEnd,
            ],
            default => [
                'start' => $now->copy()->subMonths((int) $period)->startOfMonth(),
                'end' => $now->copy()->endOfDay(),
            ],
        };
    }

    public function getPreviousPeriodDateRange(array $currentRange): array
    {
        $duration = $currentRange['start']->diffInDays($currentRange['end']);

        return [
            'start' => $currentRange['start']->copy()->subDays($duration + 1),
            'end' => $currentRange['start']->copy()->subDay(),
        ];
    }

    public function getReportTotals(int $landlordId, array $dateRange, ?int $buildingId = null): array
    {
        $filters = $this->buildCacheFilters($dateRange, $buildingId);

        return FinanceCacheService::rememberReport('totals', $landlordId, $filters, function () use ($landlordId, $dateRange, $buildingId) {
            $invoiceQuery = Invoice::where('landlord_id', $landlordId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

            $paymentQuery = Payment::withArchived()->where('landlord_id', $landlordId)
                ->where('is_voided', false)
                ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']]);

            $expenseQuery = Expense::where('landlord_id', $landlordId)
                ->whereBetween('expense_date', [$dateRange['start'], $dateRange['end']]);

            if ($buildingId) {
                $invoiceQuery->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
                $paymentQuery->whereHas('lease.unit', fn ($q) => $q->where('building_id', $buildingId));
                $expenseQuery->where('building_id', $buildingId);
            }

            $invoiced = $invoiceQuery->sum('total_due');
            $collected = $paymentQuery->sum('amount');
            $expenses = $expenseQuery->sum('amount');

            return [
                'invoiced' => round($invoiced, 2),
                'collected' => round($collected, 2),
                'expenses' => round($expenses, 2),
                'net' => round($collected - $expenses, 2),
                'collection_rate' => $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0,
            ];
        });
    }
}
