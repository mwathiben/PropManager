<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Refund;
use App\Traits\DatabaseAgnosticQueries;
use Illuminate\Support\Collection;

class FinanceStatsService
{
    use DatabaseAgnosticQueries;

    public function getOverviewStats(int $landlordId): array
    {
        $suffix = now()->format('Y-m');

        return FinanceCacheService::rememberStats('overview', $landlordId, function () use ($landlordId) {
            $now = now();
            $currentMonth = $now->month;
            $currentYear = $now->year;
            $prevMonth = $now->copy()->subMonth()->month;
            $prevYear = $now->copy()->subMonth()->year;

            $paymentMonthSql = $this->getMonthSql('payment_date');
            $paymentYearSql = $this->getYearSql('payment_date');

            $paymentStats = Payment::where('landlord_id', $landlordId)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN {$paymentMonthSql} = ? AND {$paymentYearSql} = ? THEN amount ELSE 0 END), 0) as this_month,
                    COALESCE(SUM(CASE WHEN {$paymentMonthSql} = ? AND {$paymentYearSql} = ? THEN amount ELSE 0 END), 0) as last_month
                ", [$currentMonth, $currentYear, $prevMonth, $prevYear])
                ->first();

            $invoiceMonthSql = $this->getMonthSql('created_at');
            $invoiceYearSql = $this->getYearSql('created_at');

            $invoiceStats = Invoice::where('landlord_id', $landlordId)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'partial', 'overdue') THEN total_due - amount_paid ELSE 0 END), 0) as pending_amount,
                    COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
                    COALESCE(SUM(CASE WHEN {$invoiceMonthSql} = ? AND {$invoiceYearSql} = ? THEN total_due ELSE 0 END), 0) as invoiced_this_month,
                    COALESCE(SUM(CASE WHEN {$invoiceMonthSql} = ? AND {$invoiceYearSql} = ? THEN amount_paid ELSE 0 END), 0) as collected_this_month
                ", [$currentMonth, $currentYear, $currentMonth, $currentYear])
                ->first();

            $thisMonth = (float) $paymentStats->this_month;
            $lastMonth = (float) $paymentStats->last_month;
            $invoicedThisMonth = (float) $invoiceStats->invoiced_this_month;
            $collectedThisMonth = (float) $invoiceStats->collected_this_month;

            $collectionRate = $invoicedThisMonth > 0
                ? round(($collectedThisMonth / $invoicedThisMonth) * 100, 1)
                : 0;

            $monthTrend = $lastMonth > 0
                ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
                : 0;

            return [
                'this_month' => round($thisMonth, 2),
                'last_month' => round($lastMonth, 2),
                'month_trend' => $monthTrend,
                'pending_amount' => round((float) $invoiceStats->pending_amount, 2),
                'overdue_count' => (int) $invoiceStats->overdue_count,
                'collection_rate' => $collectionRate,
            ];
        }, $suffix);
    }

    public function getHubStats(int $landlordId): array
    {
        return FinanceCacheService::rememberStats('hub', $landlordId, function () use ($landlordId) {
            $overviewStats = $this->getOverviewStats($landlordId);
            $arrearsStats = $this->getArrearsStats($landlordId);
            $now = now();
            $currentMonth = $now->month;
            $currentYear = $now->year;

            $leaseStats = Lease::where('landlord_id', $landlordId)
                ->selectRaw('
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count,
                    COALESCE(SUM(CASE WHEN is_active = 1 THEN deposit_amount ELSE 0 END), 0) as deposits_held
                ')
                ->first();

            $invoiceStats = Invoice::where('landlord_id', $landlordId)
                ->selectRaw("
                    COUNT(*) as total_count,
                    COUNT(CASE WHEN status IN ('sent', 'partial', 'overdue') THEN 1 END) as pending_count
                ")
                ->first();

            $paymentMonthSql = $this->getMonthSql('payment_date');
            $paymentYearSql = $this->getYearSql('payment_date');

            $paymentStats = Payment::where('landlord_id', $landlordId)
                ->selectRaw("
                    COUNT(CASE WHEN {$paymentMonthSql} = ? AND {$paymentYearSql} = ? THEN 1 END) as this_month_count,
                    COUNT(CASE WHEN invoice_id IS NULL THEN 1 END) as unreconciled_count
                ", [$currentMonth, $currentYear])
                ->first();

            $expenseMonthSql = $this->getMonthSql('expense_date');
            $expenseYearSql = $this->getYearSql('expense_date');

            $expenseStats = Expense::where('landlord_id', $landlordId)
                ->selectRaw("
                    COUNT(CASE WHEN {$expenseMonthSql} = ? AND {$expenseYearSql} = ? THEN 1 END) as this_month_count,
                    COALESCE(SUM(CASE WHEN {$expenseMonthSql} = ? AND {$expenseYearSql} = ? THEN amount ELSE 0 END), 0) as this_month_amount
                ", [$currentMonth, $currentYear, $currentMonth, $currentYear])
                ->first();

            $refundsPending = Refund::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->count();

            return [
                'revenue_mtd' => $overviewStats['this_month'],
                'outstanding_balance' => $overviewStats['pending_amount'],
                'collection_rate' => $overviewStats['collection_rate'],
                'active_leases' => (int) $leaseStats->active_count,
                'month_trend' => $overviewStats['month_trend'],

                'invoices_count' => (int) $invoiceStats->total_count,
                'invoices_pending' => (int) $invoiceStats->pending_count,
                'payments_this_month' => (int) $paymentStats->this_month_count,
                'deposits_held' => (float) $leaseStats->deposits_held,

                'expenses_this_month' => round((float) $expenseStats->this_month_amount, 2),
                'expenses_count' => (int) $expenseStats->this_month_count,
                'refunds_pending' => $refundsPending,

                'total_arrears' => $arrearsStats['total_arrears'],
                'tenants_in_arrears' => $arrearsStats['tenants_in_arrears'],
                'unreconciled_count' => (int) $paymentStats->unreconciled_count,
            ];
        });
    }

    public function getArrearsStats(int $landlordId): array
    {
        return FinanceCacheService::rememberStats('arrears', $landlordId, function () use ($landlordId) {
            $today = now()->format('Y-m-d');
            $daysDiffSql = $this->getDaysBetweenSql('due_date', $today);

            $stats = Invoice::where('landlord_id', $landlordId)
                ->where('status', 'overdue')
                ->selectRaw("
                    COALESCE(SUM(total_due - amount_paid), 0) as total_arrears,
                    COUNT(DISTINCT lease_id) as tenants_in_arrears,
                    COUNT(*) as overdue_count,
                    COALESCE(SUM(CASE
                        WHEN due_date IS NOT NULL AND {$daysDiffSql} <= 30
                        THEN total_due - amount_paid ELSE 0 END), 0) as age_0_30,
                    COALESCE(SUM(CASE
                        WHEN due_date IS NOT NULL AND {$daysDiffSql} > 30
                        AND {$daysDiffSql} <= 60
                        THEN total_due - amount_paid ELSE 0 END), 0) as age_31_60,
                    COALESCE(SUM(CASE
                        WHEN due_date IS NOT NULL AND {$daysDiffSql} > 60
                        AND {$daysDiffSql} <= 90
                        THEN total_due - amount_paid ELSE 0 END), 0) as age_61_90,
                    COALESCE(SUM(CASE
                        WHEN due_date IS NOT NULL AND {$daysDiffSql} > 90
                        THEN total_due - amount_paid ELSE 0 END), 0) as age_90_plus
                ")
                ->first();

            return [
                'total_arrears' => round((float) $stats->total_arrears, 2),
                'tenants_in_arrears' => (int) $stats->tenants_in_arrears,
                'overdue_count' => (int) $stats->overdue_count,
                'age_groups' => [
                    '0_30' => round((float) $stats->age_0_30, 2),
                    '31_60' => round((float) $stats->age_31_60, 2),
                    '61_90' => round((float) $stats->age_61_90, 2),
                    '90_plus' => round((float) $stats->age_90_plus, 2),
                ],
            ];
        });
    }

    public function getDepositStats(int $landlordId): array
    {
        return FinanceCacheService::rememberStats('deposits', $landlordId, function () use ($landlordId) {
            $deposits = Lease::where('landlord_id', $landlordId)
                ->where('deposit_amount', '>', 0)
                ->selectRaw("
                    SUM(deposit_amount) as total,
                    SUM(CASE WHEN deposit_status = 'held' THEN deposit_amount ELSE 0 END) as held,
                    SUM(CASE WHEN deposit_status IN ('refunded', 'partial_refund') THEN COALESCE(deposit_refund_amount, 0) ELSE 0 END) as refunded,
                    SUM(CASE WHEN deposit_status = 'forfeited' THEN deposit_amount ELSE 0 END) as forfeited
                ")
                ->first();

            return [
                'total' => round($deposits->total ?? 0, 2),
                'held' => round($deposits->held ?? 0, 2),
                'refunded' => round($deposits->refunded ?? 0, 2),
                'forfeited' => round($deposits->forfeited ?? 0, 2),
            ];
        });
    }

    public function getLateFeeStats(int $landlordId): array
    {
        return FinanceCacheService::rememberStats('latefees', $landlordId, function () use ($landlordId) {
            $activePolicies = LateFeePolicy::where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->count();

            $totalFeesApplied = LateFee::where('landlord_id', $landlordId)
                ->where('is_waived', false)
                ->sum('fee_amount');

            $totalFeesWaived = LateFee::where('landlord_id', $landlordId)
                ->where('is_waived', true)
                ->sum('fee_amount');

            $feesThisMonth = LateFee::where('landlord_id', $landlordId)
                ->where('is_waived', false)
                ->whereMonth('applied_date', now()->month)
                ->whereYear('applied_date', now()->year)
                ->sum('fee_amount');

            return [
                'active_policies' => $activePolicies,
                'total_fees_applied' => round($totalFeesApplied, 2),
                'total_fees_waived' => round($totalFeesWaived, 2),
                'fees_this_month' => round($feesThisMonth, 2),
            ];
        });
    }

    public function getExpenseStats(int $landlordId): array
    {
        return FinanceCacheService::rememberStats('expenses', $landlordId, function () use ($landlordId) {
            $now = now();
            $currentMonth = $now->month;
            $currentYear = $now->year;
            $prevMonth = $now->copy()->subMonth()->month;
            $prevYear = $now->copy()->subMonth()->year;

            $expenseMonthSql = $this->getMonthSql('expense_date');
            $expenseYearSql = $this->getYearSql('expense_date');

            $totals = Expense::where('landlord_id', $landlordId)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN {$expenseMonthSql} = ? AND {$expenseYearSql} = ? THEN amount ELSE 0 END), 0) as this_month,
                    COALESCE(SUM(CASE WHEN {$expenseMonthSql} = ? AND {$expenseYearSql} = ? THEN amount ELSE 0 END), 0) as last_month,
                    COALESCE(SUM(CASE WHEN {$expenseYearSql} = ? THEN amount ELSE 0 END), 0) as this_year
                ", [$currentMonth, $currentYear, $prevMonth, $prevYear, $currentYear])
                ->first();

            $thisMonth = (float) $totals->this_month;
            $lastMonth = (float) $totals->last_month;

            $expenseMonthSqlJoin = $this->getMonthSql('expenses.expense_date');
            $expenseYearSqlJoin = $this->getYearSql('expenses.expense_date');

            // SCOPE-D2: bypass only the named 'landlord' scope (not all scopes).
            // The join introduces an ambiguous `landlord_id` column which TenantScope's
            // unqualified where would clash with — `expenses.landlord_id` below is the
            // intentional, qualified, security-bearing filter. Caller MUST pass an
            // auth-derived landlord id; never user input.
            $categoryBreakdown = Expense::withoutGlobalScope('landlord')
                ->where('expenses.landlord_id', $landlordId)
                ->whereRaw("{$expenseMonthSqlJoin} = ? AND {$expenseYearSqlJoin} = ?", [$currentMonth, $currentYear])
                ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
                ->selectRaw("
                    expenses.category_id,
                    COALESCE(expense_categories.name, 'Uncategorized') as name,
                    COALESCE(expense_categories.color, '#6B7280') as color,
                    SUM(expenses.amount) as amount
                ")
                ->groupBy('expenses.category_id', 'expense_categories.name', 'expense_categories.color')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'color' => $row->color,
                    'amount' => round((float) $row->amount, 2),
                ])
                ->toArray();

            $monthTrend = $lastMonth > 0
                ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
                : 0;

            return [
                'this_month' => round($thisMonth, 2),
                'last_month' => round($lastMonth, 2),
                'month_trend' => $monthTrend,
                'this_year' => round((float) $totals->this_year, 2),
                'category_breakdown' => $categoryBreakdown,
            ];
        });
    }

    public function calculateCollectionRate(int $landlordId): float
    {
        return $this->calculateCollectionRateUncached($landlordId);
    }

    private function calculateCollectionRateUncached(int $landlordId): float
    {
        $now = now();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        $invoiceMonthSql = $this->getMonthSql('created_at');
        $invoiceYearSql = $this->getYearSql('created_at');

        $totals = Invoice::where('landlord_id', $landlordId)
            ->whereRaw("{$invoiceMonthSql} = ? AND {$invoiceYearSql} = ?", [$currentMonth, $currentYear])
            ->selectRaw('COALESCE(SUM(total_due), 0) as invoiced, COALESCE(SUM(amount_paid), 0) as collected')
            ->first();

        $invoiced = (float) $totals->invoiced;

        if ($invoiced <= 0) {
            return 0;
        }

        return round(((float) $totals->collected / $invoiced) * 100, 1);
    }

    public function getRecentPayments(int $landlordId, int $limit = 5): array
    {
        return Payment::where('landlord_id', $landlordId)
            ->with([
                'lease.tenant:id,name',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ])
            ->orderBy('payment_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'payment_method' => $p->payment_method,
                'payment_date' => $p->payment_date->format('Y-m-d'),
                'tenant_name' => $p->lease?->tenant?->name ?? 'Unknown',
                'unit' => $p->lease?->unit?->unit_number ?? 'N/A',
                'building' => $p->lease?->unit?->building?->name ?? 'N/A',
                'reference' => $p->reference,
            ])
            ->toArray();
    }

    public function getRecentInvoices(int $landlordId, int $limit = 5): array
    {
        return Invoice::where('landlord_id', $landlordId)
            ->with([
                'lease.tenant:id,name',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'status' => $i->status,
                'total_due' => $i->total_due,
                'amount_paid' => $i->amount_paid,
                'due_date' => $i->due_date?->format('Y-m-d'),
                'tenant_name' => $i->lease?->tenant?->name ?? 'Unknown',
                'unit' => $i->lease?->unit?->unit_number ?? 'N/A',
                'building' => $i->lease?->unit?->building?->name ?? 'N/A',
            ])
            ->toArray();
    }

    public function getMonthlyTrend(int $landlordId, int $months = 6): array
    {
        return FinanceCacheService::rememberStats('trend', $landlordId, function () use ($landlordId, $months) {
            $startDate = now()->subMonths($months - 1)->startOfMonth();

            $paymentDateFormatSql = $this->getDateFormatSql('payment_date', '%Y-%m');

            $paymentsGrouped = Payment::withArchived()->where('landlord_id', $landlordId)
                ->where('payment_date', '>=', $startDate)
                ->selectRaw("{$paymentDateFormatSql} as month_key, SUM(amount) as collected")
                ->groupBy('month_key')
                ->pluck('collected', 'month_key');

            $invoiceDateFormatSql = $this->getDateFormatSql('created_at', '%Y-%m');

            $invoicesGrouped = Invoice::where('landlord_id', $landlordId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw("{$invoiceDateFormatSql} as month_key, SUM(total_due) as invoiced")
                ->groupBy('month_key')
                ->pluck('invoiced', 'month_key');

            $trend = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $key = $date->format('Y-m');

                $trend[] = [
                    'month' => $date->format('M'),
                    'year' => $date->format('Y'),
                    'collected' => round((float) ($paymentsGrouped[$key] ?? 0), 2),
                    'invoiced' => round((float) ($invoicesGrouped[$key] ?? 0), 2),
                ];
            }

            return $trend;
        });
    }

    public function getCollectionStatus(int $landlordId, ?float $rate = null): string
    {
        $rate = $rate ?? $this->calculateCollectionRate($landlordId);

        if ($rate >= 90) {
            return 'excellent';
        }
        if ($rate >= 75) {
            return 'good';
        }
        if ($rate >= 50) {
            return 'needs_attention';
        }

        return 'critical';
    }

    public function getPendingReconciliationCount(int $landlordId): int
    {
        return Payment::where('landlord_id', $landlordId)
            ->whereNull('invoice_id')
            ->count();
    }

    public function calculateInvoiceSummary(Collection $invoices): array
    {
        $totalDue = $invoices->sum('total_due');
        $totalPaid = $invoices->sum('amount_paid');

        return [
            'total_count' => $invoices->count(),
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'total_balance' => $totalDue - $totalPaid,
            'collection_rate' => $totalDue > 0 ? round(($totalPaid / $totalDue) * 100, 1) : 0,
        ];
    }

    public function calculatePaymentSummary(Collection $payments): array
    {
        $totalAmount = $payments->sum('amount');

        return [
            'total_count' => $payments->count(),
            'total_amount' => $totalAmount,
            'average_payment' => $payments->count() > 0 ? $totalAmount / $payments->count() : 0,
        ];
    }

    public function calculateMethodBreakdown(Collection $payments): array
    {
        return $payments->groupBy('payment_method')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ])
            ->toArray();
    }

    public function calculateExpenseSummary(Collection $expenses): array
    {
        return [
            'total_count' => $expenses->count(),
            'total_amount' => $expenses->sum('amount'),
            'average_expense' => $expenses->count() > 0 ? $expenses->sum('amount') / $expenses->count() : 0,
            'recurring_count' => $expenses->where('is_recurring', true)->count(),
        ];
    }

    public function calculateExpenseCategoryBreakdown(Collection $expenses): array
    {
        // PERF-R3: defense-in-depth eager-load. Callers should pass expenses
        // with category already loaded; if not, this single loadMissing
        // collapses M lazy SELECTs (one per distinct category) into one
        // batched query.
        if ($expenses->isNotEmpty()) {
            $expenses->loadMissing('category:id,name,color');
        }

        return $expenses->groupBy('category_id')
            ->map(fn ($group) => [
                'name' => $group->first()->category?->name ?? 'Uncategorized',
                'color' => $group->first()->category?->color ?? '#6B7280',
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ])
            ->values()
            ->toArray();
    }
}
