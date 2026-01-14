<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Collection;

class FinanceStatsService
{
    public function getOverviewStats(int $landlordId): array
    {
        $suffix = now()->format('Y-m');

        return FinanceCacheService::rememberStats('overview', $landlordId, function () use ($landlordId) {
            $now = now();
            $currentMonth = sprintf('%02d', $now->month);
            $currentYear = (string) $now->year;
            $prevMonth = sprintf('%02d', $now->copy()->subMonth()->month);
            $prevYear = (string) $now->copy()->subMonth()->year;

            $paymentStats = Payment::where('landlord_id', $landlordId)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN strftime("%m", payment_date) = ? AND strftime("%Y", payment_date) = ? THEN amount ELSE 0 END), 0) as this_month,
                    COALESCE(SUM(CASE WHEN strftime("%m", payment_date) = ? AND strftime("%Y", payment_date) = ? THEN amount ELSE 0 END), 0) as last_month
                ', [$currentMonth, $currentYear, $prevMonth, $prevYear])
                ->first();

            $invoiceStats = Invoice::where('landlord_id', $landlordId)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN status IN ("sent", "partial", "overdue") THEN total_due - amount_paid ELSE 0 END), 0) as pending_amount,
                    COUNT(CASE WHEN status = "overdue" THEN 1 END) as overdue_count,
                    COALESCE(SUM(CASE WHEN strftime("%m", created_at) = ? AND strftime("%Y", created_at) = ? THEN total_due ELSE 0 END), 0) as invoiced_this_month,
                    COALESCE(SUM(CASE WHEN strftime("%m", created_at) = ? AND strftime("%Y", created_at) = ? THEN amount_paid ELSE 0 END), 0) as collected_this_month
                ', [$currentMonth, $currentYear, $currentMonth, $currentYear])
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

            return [
                'revenue_mtd' => $overviewStats['this_month'],
                'outstanding_balance' => $overviewStats['pending_amount'],
                'collection_rate' => $overviewStats['collection_rate'],
                'active_leases' => Lease::where('landlord_id', $landlordId)->where('is_active', true)->count(),
                'month_trend' => $overviewStats['month_trend'],

                'invoices_count' => Invoice::where('landlord_id', $landlordId)->count(),
                'invoices_pending' => Invoice::where('landlord_id', $landlordId)
                    ->whereIn('status', ['sent', 'partial', 'overdue'])->count(),
                'payments_this_month' => Payment::where('landlord_id', $landlordId)
                    ->whereMonth('payment_date', $now->month)
                    ->whereYear('payment_date', $now->year)->count(),
                'deposits_held' => Lease::where('landlord_id', $landlordId)
                    ->where('is_active', true)->sum('deposit_amount'),

                'expenses_this_month' => Expense::where('landlord_id', $landlordId)
                    ->whereMonth('expense_date', $now->month)
                    ->whereYear('expense_date', $now->year)->sum('amount'),
                'expenses_count' => Expense::where('landlord_id', $landlordId)
                    ->whereMonth('expense_date', $now->month)
                    ->whereYear('expense_date', $now->year)->count(),
                'refunds_pending' => Refund::where('landlord_id', $landlordId)
                    ->where('status', 'pending')->count(),

                'total_arrears' => $arrearsStats['total_arrears'],
                'tenants_in_arrears' => $arrearsStats['tenants_in_arrears'],
                'unreconciled_count' => $this->getPendingReconciliationCount($landlordId),
            ];
        });
    }

    public function getArrearsStats(int $landlordId): array
    {
        return FinanceCacheService::rememberStats('arrears', $landlordId, function () use ($landlordId) {
            $overdueInvoices = Invoice::where('landlord_id', $landlordId)
                ->where('status', 'overdue')
                ->get();

            $totalArrears = $overdueInvoices->sum(fn ($i) => $i->total_due - $i->amount_paid);
            $tenantsInArrears = $overdueInvoices->pluck('lease_id')->unique()->count();

            $ageGroups = [
                '0_30' => 0,
                '31_60' => 0,
                '61_90' => 0,
                '90_plus' => 0,
            ];

            foreach ($overdueInvoices as $invoice) {
                if (! $invoice->due_date) {
                    continue;
                }
                $daysOverdue = now()->diffInDays($invoice->due_date, false) * -1;
                $balance = $invoice->total_due - $invoice->amount_paid;

                if ($daysOverdue <= 30) {
                    $ageGroups['0_30'] += $balance;
                } elseif ($daysOverdue <= 60) {
                    $ageGroups['31_60'] += $balance;
                } elseif ($daysOverdue <= 90) {
                    $ageGroups['61_90'] += $balance;
                } else {
                    $ageGroups['90_plus'] += $balance;
                }
            }

            return [
                'total_arrears' => round($totalArrears, 2),
                'tenants_in_arrears' => $tenantsInArrears,
                'overdue_count' => $overdueInvoices->count(),
                'age_groups' => array_map(fn ($v) => round($v, 2), $ageGroups),
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

            $thisMonth = Expense::where('landlord_id', $landlordId)
                ->whereMonth('expense_date', $now->month)
                ->whereYear('expense_date', $now->year)
                ->sum('amount');

            $lastMonth = Expense::where('landlord_id', $landlordId)
                ->whereMonth('expense_date', $now->copy()->subMonth()->month)
                ->whereYear('expense_date', $now->copy()->subMonth()->year)
                ->sum('amount');

            $thisYear = Expense::where('landlord_id', $landlordId)
                ->whereYear('expense_date', $now->year)
                ->sum('amount');

            $categoryBreakdown = Expense::where('landlord_id', $landlordId)
                ->whereMonth('expense_date', $now->month)
                ->whereYear('expense_date', $now->year)
                ->with('category')
                ->get()
                ->groupBy('category_id')
                ->map(fn ($group) => [
                    'name' => $group->first()->category?->name ?? 'Uncategorized',
                    'color' => $group->first()->category?->color ?? '#6B7280',
                    'amount' => $group->sum('amount'),
                ])
                ->values()
                ->toArray();

            $monthTrend = $lastMonth > 0
                ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
                : 0;

            return [
                'this_month' => round($thisMonth, 2),
                'last_month' => round($lastMonth, 2),
                'month_trend' => $monthTrend,
                'this_year' => round($thisYear, 2),
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

        $invoiced = Invoice::where('landlord_id', $landlordId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('total_due');

        if ($invoiced <= 0) {
            return 0;
        }

        $collected = Invoice::where('landlord_id', $landlordId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('amount_paid');

        return round(($collected / $invoiced) * 100, 1);
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

            $paymentsGrouped = Payment::where('landlord_id', $landlordId)
                ->where('payment_date', '>=', $startDate)
                ->selectRaw("strftime('%Y-%m', payment_date) as month_key, SUM(amount) as collected")
                ->groupBy('month_key')
                ->pluck('collected', 'month_key');

            $invoicesGrouped = Invoice::where('landlord_id', $landlordId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw("strftime('%Y-%m', created_at) as month_key, SUM(total_due) as invoiced")
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
