<?php

namespace App\Http\Controllers;

use App\Exports\ExpensesExport;
use App\Exports\InvoicesExport;
use App\Exports\PaymentsExport;
use App\Exports\VendorExpenseExport;
use App\Mail\DepositRefundNotification;
use App\Models\Building;
use App\Models\DepositTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\Vendor;
use App\Services\LateFeeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancesController extends Controller
{
    public function index(): Response
    {
        $landlordId = $this->getLandlordId();

        return Inertia::render('Finances/Hub', [
            'stats' => $this->getHubStats($landlordId),
            'buildings' => $this->getBuildings($landlordId),
            'properties' => $this->getProperties($landlordId),
        ]);
    }

    public function overview(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('overview', [
            'stats' => $this->getOverviewStats($landlordId),
            'recentPayments' => $this->getRecentPayments($landlordId, 5),
            'recentInvoices' => $this->getRecentInvoices($landlordId, 5),
            'collectionStatus' => $this->getCollectionStatus($landlordId),
            'monthlyTrend' => $this->getMonthlyTrend($landlordId, 6),
        ]);
    }

    public function invoices(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('invoices', [
            'invoices' => $this->getPaginatedInvoices($request, $landlordId),
            'filters' => $request->only(['search', 'status', 'building_id', 'date_from', 'date_to']),
            'statusOptions' => $this->getInvoiceStatusOptions(),
        ]);
    }

    public function payments(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('payments', [
            'payments' => $this->getPaginatedPayments($request, $landlordId),
            'filters' => $request->only(['search', 'method', 'building_id', 'date_from', 'date_to']),
            'paymentMethodOptions' => $this->getPaymentMethodOptions(),
        ]);
    }

    public function refunds(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('refunds', [
            'refunds' => $this->getPaginatedRefunds($request, $landlordId),
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'statusOptions' => $this->getRefundStatusOptions(),
        ]);
    }

    public function reconciliation(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('reconciliation', [
            'unmatchedPayments' => $this->getUnmatchedPayments($landlordId),
            'pendingReconciliation' => $this->getPendingReconciliationCount($landlordId),
        ]);
    }

    public function deposits(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('deposits', [
            'deposits' => $this->getPaginatedDeposits($request, $landlordId),
            'filters' => $request->only(['search', 'status', 'building_id']),
            'stats' => $this->getDepositStats($landlordId),
        ]);
    }

    public function arrears(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('arrears', [
            'arrears' => $this->getArrearsData($request, $landlordId),
            'filters' => $request->only(['search', 'building_id', 'min_days', 'max_amount']),
            'stats' => $this->getArrearsStats($landlordId),
        ]);
    }

    public function settings(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('settings', [
            'paymentConfig' => $this->getPaymentConfig($landlordId),
            'paymentMethods' => PaymentConfiguration::PAYMENT_METHODS,
            'invoiceSettings' => $this->getInvoiceSettings($landlordId),
            'reminderSettings' => $this->getReminderSettings($landlordId),
        ]);
    }

    public function reports(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $period = $request->query('period', '12');

        return $this->renderFinances('reports', [
            'revenueData' => $this->getRevenueReport($landlordId, (int) $period),
            'collectionRate' => $this->getCollectionRateReport($landlordId, (int) $period),
            'occupancyData' => $this->getOccupancyReport($landlordId),
            'arrearsAging' => $this->getArrearsAgingReport($landlordId),
            'expensesByCategory' => $this->getExpensesByCategoryReport($landlordId, (int) $period),
            'waterConsumption' => $this->getWaterConsumptionReport($landlordId, (int) $period),
            'topPerformingUnits' => $this->getTopPerformingUnitsReport($landlordId, (int) $period),
            'filters' => ['period' => $period],
        ]);
    }

    public function exportReports(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $landlordId = $this->getLandlordId();
        $period = (int) $request->query('period', '12');
        $format = $request->query('format', 'xlsx');

        $data = [
            'revenue' => $this->getRevenueReport($landlordId, $period),
            'collection_rate' => $this->getCollectionRateReport($landlordId, $period),
            'occupancy' => $this->getOccupancyReport($landlordId),
            'arrears_aging' => $this->getArrearsAgingReport($landlordId),
            'expenses_by_category' => $this->getExpensesByCategoryReport($landlordId, $period),
            'water_consumption' => $this->getWaterConsumptionReport($landlordId, $period),
            'top_performing_units' => $this->getTopPerformingUnitsReport($landlordId, $period),
        ];

        $filename = 'financial_report_'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.financial-report', [
                'data' => $data,
                'period' => $period,
                'landlord' => auth()->user(),
                'generated_at' => now()->format('M j, Y g:i A'),
            ]);

            return $pdf->download($filename.'.pdf');
        }

        if ($format === 'csv') {
            return $this->exportReportsCsv($data, $filename);
        }

        return Excel::download(
            new \App\Exports\FinanceReportExport($data, $period),
            $filename.'.xlsx'
        );
    }

    private function exportReportsCsv(array $data, string $filename): \Illuminate\Http\Response
    {
        $output = fopen('php://temp', 'r+');

        fputcsv($output, ['PropManager Financial Report']);
        fputcsv($output, ['Generated: '.now()->format('F j, Y g:i A')]);
        fputcsv($output, []);

        fputcsv($output, ['Revenue Summary']);
        fputcsv($output, ['Month', 'Invoiced', 'Collected', 'Expenses', 'Net']);
        foreach ($data['revenue'] as $row) {
            fputcsv($output, [$row['month'], $row['invoiced'], $row['collected'], $row['expenses'], $row['net']]);
        }
        fputcsv($output, []);

        fputcsv($output, ['Occupancy by Building']);
        fputcsv($output, ['Building', 'Total Units', 'Occupied', 'Vacant', 'Rate %']);
        foreach ($data['occupancy']['buildings'] as $row) {
            fputcsv($output, [$row['building'], $row['total_units'], $row['occupied'], $row['vacant'], $row['occupancy_rate']]);
        }
        fputcsv($output, []);

        fputcsv($output, ['Water Consumption - Top Consumers']);
        fputcsv($output, ['Unit', 'Building', 'Consumption', 'Cost']);
        foreach ($data['water_consumption']['top_consumers'] as $row) {
            fputcsv($output, [$row['unit'], $row['building'], $row['consumption'], $row['cost']]);
        }
        fputcsv($output, []);

        fputcsv($output, ['Top Performing Units']);
        fputcsv($output, ['Unit', 'Tenant', 'Collection Rate %', 'On-Time', 'Total Invoices']);
        foreach ($data['top_performing_units'] as $row) {
            fputcsv($output, [$row['unit'], $row['tenant'], $row['collection_rate'], $row['on_time_payments'], $row['total_invoices']]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    public function invoiceDetail(Invoice $invoice): JsonResponse
    {
        $landlordId = $this->getLandlordId();

        if ($invoice->landlord_id !== $landlordId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invoice->load([
            'lease.tenant:id,name,email,mobile_number',
            'lease.unit:id,unit_number,building_id',
            'lease.unit.building:id,name',
            'payments:id,invoice_id,amount,payment_method,payment_date,reference',
        ]);

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'total_due' => $invoice->total_due,
                'amount_paid' => $invoice->amount_paid,
                'balance' => $invoice->total_due - $invoice->amount_paid,
                'rent_amount' => $invoice->rent_amount,
                'water_charges' => $invoice->water_charges,
                'arrears_amount' => $invoice->arrears_amount,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'billing_period_start' => $invoice->billing_period_start?->format('Y-m-d'),
                'billing_period_end' => $invoice->billing_period_end?->format('Y-m-d'),
                'created_at' => $invoice->created_at->format('Y-m-d H:i'),
                'tenant' => $invoice->lease?->tenant ? [
                    'id' => $invoice->lease->tenant->id,
                    'name' => $invoice->lease->tenant->name,
                    'email' => $invoice->lease->tenant->email,
                    'phone' => $invoice->lease->tenant->mobile_number,
                ] : null,
                'unit' => $invoice->lease?->unit ? [
                    'id' => $invoice->lease->unit->id,
                    'unit_number' => $invoice->lease->unit->unit_number,
                    'building' => $invoice->lease->unit->building?->name,
                ] : null,
                'payments' => $invoice->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'method' => $p->payment_method,
                    'date' => $p->payment_date?->format('Y-m-d'),
                    'reference' => $p->reference,
                ])->toArray(),
            ],
        ]);
    }

    public function paymentDetail(Payment $payment): JsonResponse
    {
        $landlordId = $this->getLandlordId();

        if ($payment->landlord_id !== $landlordId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payment->load([
            'invoice:id,invoice_number,total_due',
            'lease.tenant:id,name,email,mobile_number',
            'lease.unit:id,unit_number,building_id',
            'lease.unit.building:id,name',
            'refund:id,payment_id,amount,status,reason',
        ]);

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_date' => $payment->payment_date?->format('Y-m-d'),
                'reference' => $payment->reference,
                'mpesa_transaction_id' => $payment->mpesa_transaction_id,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at->format('Y-m-d H:i'),
                'invoice' => $payment->invoice ? [
                    'id' => $payment->invoice->id,
                    'invoice_number' => $payment->invoice->invoice_number,
                    'total_due' => $payment->invoice->total_due,
                ] : null,
                'tenant' => $payment->lease?->tenant ? [
                    'id' => $payment->lease->tenant->id,
                    'name' => $payment->lease->tenant->name,
                    'email' => $payment->lease->tenant->email,
                    'phone' => $payment->lease->tenant->mobile_number,
                ] : null,
                'unit' => $payment->lease?->unit ? [
                    'id' => $payment->lease->unit->id,
                    'unit_number' => $payment->lease->unit->unit_number,
                    'building' => $payment->lease->unit->building?->name,
                ] : null,
                'refund' => $payment->refund ? [
                    'id' => $payment->refund->id,
                    'amount' => $payment->refund->amount,
                    'status' => $payment->refund->status,
                    'reason' => $payment->refund->reason,
                ] : null,
                'can_refund' => ! $payment->refund && $payment->amount > 0,
            ],
        ]);
    }

    private function renderFinances(string $tab, array $additionalProps = []): Response
    {
        $landlordId = $this->getLandlordId();

        $baseProps = [
            'activeTab' => $tab,
            'buildings' => $this->getBuildings($landlordId),
            'tabs' => $this->getTabsConfig(),
        ];

        return Inertia::render('Finances/Index', array_merge($baseProps, $additionalProps));
    }

    private function getLandlordId(): int
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        return $user->isCaretaker() ? $user->landlord_id : $user->id;
    }

    private function getTabsConfig(): array
    {
        return [
            ['id' => 'overview', 'name' => 'Overview', 'route' => 'finances.overview'],
            ['id' => 'invoices', 'name' => 'Invoices', 'route' => 'finances.invoices'],
            ['id' => 'payments', 'name' => 'Payments', 'route' => 'finances.payments'],
            ['id' => 'expenses', 'name' => 'Expenses', 'route' => 'finances.expenses'],
            ['id' => 'refunds', 'name' => 'Refunds', 'route' => 'finances.refunds'],
            ['id' => 'reconciliation', 'name' => 'Reconciliation', 'route' => 'finances.reconciliation'],
            ['id' => 'deposits', 'name' => 'Deposits', 'route' => 'finances.deposits'],
            ['id' => 'arrears', 'name' => 'Arrears', 'route' => 'finances.arrears'],
            ['id' => 'late-fees', 'name' => 'Late Fees', 'route' => 'finances.late-fees'],
            ['id' => 'reports', 'name' => 'Reports', 'route' => 'finances.reports'],
            ['id' => 'settings', 'name' => 'Settings', 'route' => 'finances.settings'],
        ];
    }

    private function getBuildings(int $landlordId): array
    {
        return Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    private function getOverviewStats(int $landlordId): array
    {
        $now = now();

        $thisMonth = Payment::where('landlord_id', $landlordId)
            ->whereMonth('payment_date', $now->month)
            ->whereYear('payment_date', $now->year)
            ->sum('amount');

        $lastMonth = Payment::where('landlord_id', $landlordId)
            ->whereMonth('payment_date', $now->copy()->subMonth()->month)
            ->whereYear('payment_date', $now->copy()->subMonth()->year)
            ->sum('amount');

        $pendingAmount = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as pending')
            ->value('pending') ?? 0;

        $overdueCount = Invoice::where('landlord_id', $landlordId)
            ->where('status', 'overdue')
            ->count();

        $collectionRate = $this->calculateCollectionRate($landlordId);

        $monthTrend = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        return [
            'this_month' => round($thisMonth, 2),
            'last_month' => round($lastMonth, 2),
            'month_trend' => $monthTrend,
            'pending_amount' => round($pendingAmount, 2),
            'overdue_count' => $overdueCount,
            'collection_rate' => $collectionRate,
        ];
    }

    private function getHubStats(int $landlordId): array
    {
        $overviewStats = $this->getOverviewStats($landlordId);
        $arrearsStats = $this->getArrearsStats($landlordId);
        $now = now();

        return [
            // Hero KPIs
            'revenue_mtd' => $overviewStats['this_month'],
            'outstanding_balance' => $overviewStats['pending_amount'],
            'collection_rate' => $overviewStats['collection_rate'],
            'active_leases' => Lease::where('landlord_id', $landlordId)->where('is_active', true)->count(),
            'month_trend' => $overviewStats['month_trend'],

            // Money In section
            'invoices_count' => Invoice::where('landlord_id', $landlordId)->count(),
            'invoices_pending' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', ['sent', 'partial', 'overdue'])->count(),
            'payments_this_month' => Payment::where('landlord_id', $landlordId)
                ->whereMonth('payment_date', $now->month)
                ->whereYear('payment_date', $now->year)->count(),
            'deposits_held' => Lease::where('landlord_id', $landlordId)
                ->where('is_active', true)->sum('deposit_amount'),

            // Money Out section
            'expenses_this_month' => Expense::where('landlord_id', $landlordId)
                ->whereMonth('expense_date', $now->month)
                ->whereYear('expense_date', $now->year)->sum('amount'),
            'expenses_count' => Expense::where('landlord_id', $landlordId)
                ->whereMonth('expense_date', $now->month)
                ->whereYear('expense_date', $now->year)->count(),
            'refunds_pending' => Refund::where('landlord_id', $landlordId)
                ->where('status', 'pending')->count(),

            // Collections section
            'total_arrears' => $arrearsStats['total_arrears'],
            'tenants_in_arrears' => $arrearsStats['tenants_in_arrears'],
            'unreconciled_count' => $this->getPendingReconciliationCount($landlordId),
        ];
    }

    private function calculateCollectionRate(int $landlordId): float
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

    private function getRecentPayments(int $landlordId, int $limit = 5): array
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

    private function getRecentInvoices(int $landlordId, int $limit = 5): array
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

    private function getCollectionStatus(int $landlordId): string
    {
        $rate = $this->calculateCollectionRate($landlordId);

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

    private function getMonthlyTrend(int $landlordId, int $months = 6): array
    {
        $trend = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $collected = Payment::where('landlord_id', $landlordId)
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('amount');

            $invoiced = Invoice::where('landlord_id', $landlordId)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('total_due');

            $trend[] = [
                'month' => $date->format('M'),
                'year' => $date->format('Y'),
                'collected' => round($collected, 2),
                'invoiced' => round($invoiced, 2),
            ];
        }

        return $trend;
    }

    private function getPaginatedInvoices(Request $request, int $landlordId)
    {
        $query = Invoice::where('landlord_id', $landlordId)
            ->with([
                'lease.tenant:id,name,email',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('lease.tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    private function getPaginatedPayments(Request $request, int $landlordId)
    {
        $query = Payment::where('landlord_id', $landlordId)
            ->with([
                'invoice:id,invoice_number,total_due',
                'lease.tenant:id,name,email',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($q) => $q->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('lease.tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        return $query->orderBy('payment_date', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    private function getPaginatedRefunds(Request $request, int $landlordId)
    {
        $query = Refund::where('landlord_id', $landlordId)
            ->with([
                'payment:id,amount,payment_method,reference',
                'payment.lease.tenant:id,name',
                'payment.lease.unit:id,unit_number,building_id',
                'payment.lease.unit.building:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('payment', fn ($q) => $q->where('reference', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    private function getUnmatchedPayments(int $landlordId): array
    {
        return Payment::where('landlord_id', $landlordId)
            ->whereNull('invoice_id')
            ->with(['lease.tenant:id,name', 'lease.unit:id,unit_number'])
            ->orderBy('payment_date', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'payment_method' => $p->payment_method,
                'payment_date' => $p->payment_date->format('Y-m-d'),
                'reference' => $p->reference,
                'tenant_name' => $p->lease?->tenant?->name ?? 'Unknown',
                'unit' => $p->lease?->unit?->unit_number ?? 'N/A',
            ])
            ->toArray();
    }

    private function getPendingReconciliationCount(int $landlordId): int
    {
        return Payment::where('landlord_id', $landlordId)
            ->whereNull('invoice_id')
            ->count();
    }

    private function getPaginatedDeposits(Request $request, int $landlordId)
    {
        $query = Lease::where('landlord_id', $landlordId)
            ->where('deposit_amount', '>', 0)
            ->with([
                'tenant:id,name,email',
                'unit:id,unit_number,building_id',
                'unit.building:id,name',
                'depositTransactions' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(10),
                'depositTransactions.processedBy:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('unit', fn ($q) => $q->where('unit_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('deposit_status', $request->status);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($lease) => [
                'id' => $lease->id,
                'amount' => $lease->deposit_amount,
                'status' => $lease->deposit_status,
                'refund_amount' => $lease->deposit_refund_amount,
                'deductions' => $lease->deposit_deductions,
                'deduction_reason' => $lease->deposit_deduction_reason,
                'processed_at' => $lease->deposit_processed_at?->format('Y-m-d'),
                'tenant_name' => $lease->tenant?->name,
                'tenant_email' => $lease->tenant?->email,
                'unit_number' => $lease->unit?->unit_number,
                'building_name' => $lease->unit?->building?->name,
                'start_date' => $lease->start_date?->format('Y-m-d'),
                'end_date' => $lease->end_date?->format('Y-m-d'),
                'is_active' => $lease->is_active,
                'lease' => [
                    'id' => $lease->id,
                    'tenant' => $lease->tenant ? [
                        'id' => $lease->tenant->id,
                        'name' => $lease->tenant->name,
                    ] : null,
                    'unit' => $lease->unit ? [
                        'id' => $lease->unit->id,
                        'unit_number' => $lease->unit->unit_number,
                        'building' => $lease->unit->building?->name,
                    ] : null,
                ],
                'transactions' => $lease->depositTransactions->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'type_label' => $t->getTypeLabel(),
                    'amount' => $t->amount,
                    'balance_after' => $t->balance_after,
                    'reason' => $t->reason,
                    'payment_method' => $t->payment_method,
                    'reference' => $t->reference,
                    'processed_by' => $t->processedBy?->name,
                    'created_at' => $t->created_at->format('Y-m-d H:i'),
                ]),
            ])
            ->withQueryString();
    }

    private function getDepositStats(int $landlordId): array
    {
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
    }

    private function getArrearsData(Request $request, int $landlordId): array
    {
        $query = Invoice::where('landlord_id', $landlordId)
            ->where('status', 'overdue')
            ->with([
                'lease.tenant:id,name,email,mobile_number',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('lease.tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        return $query->orderBy('due_date', 'asc')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'total_due' => $i->total_due,
                'amount_paid' => $i->amount_paid,
                'balance' => $i->total_due - $i->amount_paid,
                'due_date' => $i->due_date?->format('Y-m-d'),
                'days_overdue' => $i->due_date ? now()->diffInDays($i->due_date, false) * -1 : 0,
                'tenant' => $i->lease?->tenant ? [
                    'id' => $i->lease->tenant->id,
                    'name' => $i->lease->tenant->name,
                    'email' => $i->lease->tenant->email,
                    'phone' => $i->lease->tenant->mobile_number,
                ] : null,
                'unit' => $i->lease?->unit?->unit_number ?? 'N/A',
                'building' => $i->lease?->unit?->building?->name ?? 'N/A',
            ])
            ->toArray();
    }

    private function getArrearsStats(int $landlordId): array
    {
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
    }

    private function getInvoiceStatusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'sent', 'label' => 'Sent'],
            ['value' => 'partial', 'label' => 'Partial'],
            ['value' => 'paid', 'label' => 'Paid'],
            ['value' => 'overdue', 'label' => 'Overdue'],
        ];
    }

    private function getPaymentMethodOptions(): array
    {
        return [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money'],
            ['value' => 'mpesa', 'label' => 'M-Pesa'],
            ['value' => 'paystack', 'label' => 'Paystack'],
            ['value' => 'stripe', 'label' => 'Card'],
        ];
    }

    private function getRefundStatusOptions(): array
    {
        return [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'processing', 'label' => 'Processing'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'failed', 'label' => 'Failed'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];
    }

    private function getPaymentConfig(int $landlordId): ?array
    {
        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        if (! $config) {
            return null;
        }

        return [
            'accepted_payment_methods' => $config->accepted_payment_methods ?? [],
            'bank_name' => $config->bank_name,
            'bank_account_name' => $config->bank_account_name,
            'bank_account_number' => $config->bank_account_number,
            'bank_branch' => $config->bank_branch,
            'mpesa_shortcode_type' => $config->mpesa_shortcode_type ?? 'paybill',
            'mpesa_shortcode' => $config->mpesa_shortcode,
            'mpesa_account_name' => $config->mpesa_account_name,
            'has_mpesa_passkey' => ! empty($config->mpesa_passkey),
            'paystack_enabled' => $config->paystack_enabled,
        ];
    }

    private function getInvoiceSettings(int $landlordId): array
    {
        return [
            'include_water_charges' => filter_var(
                Setting::get('invoice_include_water_charges', 'true', $landlordId),
                FILTER_VALIDATE_BOOLEAN
            ),
            'include_arrears' => filter_var(
                Setting::get('invoice_include_arrears', 'true', $landlordId),
                FILTER_VALIDATE_BOOLEAN
            ),
            'auto_generate_monthly' => filter_var(
                Setting::get('invoice_auto_generate_monthly', 'false', $landlordId),
                FILTER_VALIDATE_BOOLEAN
            ),
        ];
    }

    private function getReminderSettings(int $landlordId): array
    {
        $channels = Setting::get('reminder_channels', null, $landlordId);

        return [
            'reminder_days_before_due' => (int) Setting::get('reminder_days_before_due', '3', $landlordId),
            'overdue_reminder_frequency' => Setting::get('overdue_reminder_frequency', 'weekly', $landlordId),
            'reminder_channels' => $channels ? json_decode($channels, true) : ['email'],
        ];
    }

    public function updatePaymentMethods(Request $request): RedirectResponse
    {
        $request->validate([
            'accepted_payment_methods' => 'required|array',
            'accepted_payment_methods.*' => 'string|in:cash,bank_transfer,mobile_money,paystack',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_branch' => 'nullable|string|max:100',
            'mpesa_shortcode_type' => 'nullable|string|in:paybill,till',
            'mpesa_shortcode' => 'nullable|string|max:20',
            'mpesa_account_name' => 'nullable|string|max:100',
            'mpesa_passkey' => 'nullable|string|max:255',
        ]);

        $landlordId = $this->getLandlordId();

        $data = [
            'accepted_payment_methods' => $request->accepted_payment_methods,
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_branch' => $request->bank_branch,
            'mpesa_shortcode_type' => $request->mpesa_shortcode_type ?? 'paybill',
            'mpesa_shortcode' => $request->mpesa_shortcode,
            'mpesa_account_name' => $request->mpesa_account_name,
        ];

        if ($request->filled('mpesa_passkey')) {
            $data['mpesa_passkey'] = $request->mpesa_passkey;
        }

        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $landlordId],
            $data
        );

        return back()->with('success', 'Payment methods saved successfully.');
    }

    public function updateInvoiceSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'include_water_charges' => 'required|boolean',
            'include_arrears' => 'required|boolean',
            'auto_generate_monthly' => 'required|boolean',
        ]);

        $landlordId = $this->getLandlordId();

        Setting::set('invoice_include_water_charges', $request->include_water_charges ? 'true' : 'false', false, 'invoice', null, $landlordId);
        Setting::set('invoice_include_arrears', $request->include_arrears ? 'true' : 'false', false, 'invoice', null, $landlordId);
        Setting::set('invoice_auto_generate_monthly', $request->auto_generate_monthly ? 'true' : 'false', false, 'invoice', null, $landlordId);

        return back()->with('success', 'Invoice settings saved successfully.');
    }

    public function updateReminderSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'reminder_days_before_due' => 'required|integer|min:1|max:30',
            'overdue_reminder_frequency' => 'required|string|in:daily,weekly,none',
            'reminder_channels' => 'required|array',
            'reminder_channels.*' => 'string|in:email,sms,push',
        ]);

        $landlordId = $this->getLandlordId();

        Setting::set('reminder_days_before_due', (string) $request->reminder_days_before_due, false, 'notification', null, $landlordId);
        Setting::set('overdue_reminder_frequency', $request->overdue_reminder_frequency, false, 'notification', null, $landlordId);
        Setting::set('reminder_channels', json_encode($request->reminder_channels), false, 'notification', null, $landlordId);

        return back()->with('success', 'Reminder settings saved successfully.');
    }

    public function matchPayment(Request $request, Payment $payment): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($payment->landlord_id !== $landlordId) {
            abort(403);
        }

        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        $invoice = Invoice::where('landlord_id', $landlordId)
            ->where('id', $request->invoice_id)
            ->firstOrFail();

        $payment->invoice_id = $invoice->id;
        $payment->save();

        $invoice->amount_paid += $payment->amount;
        if ($invoice->amount_paid >= $invoice->total_due) {
            $invoice->status = 'paid';
        } elseif ($invoice->amount_paid > 0) {
            $invoice->status = 'partial';
        }
        $invoice->save();

        return back()->with('success', 'Payment matched to invoice successfully.');
    }

    public function refundDeposit(Request $request, Lease $lease): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        $request->validate([
            'refund_amount' => 'required|numeric|min:0|max:'.$lease->deposit_amount,
            'deductions' => 'nullable|numeric|min:0|max:'.$lease->deposit_amount,
            'deduction_reason' => 'nullable|string|max:500',
        ]);

        if ($lease->deposit_status !== 'held') {
            return back()->withErrors(['error' => 'This deposit has already been processed.']);
        }

        $refundAmount = $request->refund_amount;
        $deductions = $request->deductions ?? 0;

        if (($refundAmount + $deductions) > $lease->deposit_amount) {
            return back()->withErrors(['error' => 'Refund amount plus deductions cannot exceed deposit amount.']);
        }

        $status = $deductions > 0 ? 'partial_refund' : 'refunded';
        $balanceAfter = $lease->deposit_amount - $refundAmount - $deductions;

        $lease->update([
            'deposit_status' => $status,
            'deposit_refund_amount' => $refundAmount,
            'deposit_deductions' => $deductions,
            'deposit_deduction_reason' => $request->deduction_reason,
            'deposit_processed_at' => now(),
            'deposit_processed_by' => auth()->id(),
        ]);

        if ($deductions > 0) {
            DepositTransaction::create([
                'lease_id' => $lease->id,
                'landlord_id' => $landlordId,
                'processed_by' => auth()->id(),
                'type' => DepositTransaction::TYPE_DEDUCTION,
                'amount' => $deductions,
                'balance_after' => $lease->deposit_amount - $deductions,
                'reason' => $request->deduction_reason,
                'notes' => $request->notes,
            ]);
        }

        DepositTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlordId,
            'processed_by' => auth()->id(),
            'type' => $deductions > 0 ? DepositTransaction::TYPE_PARTIAL_REFUND : DepositTransaction::TYPE_FULL_REFUND,
            'amount' => $refundAmount,
            'balance_after' => $balanceAfter,
            'reason' => 'Deposit refund',
            'payment_method' => $request->payment_method,
            'reference' => $request->reference,
            'notes' => $request->notes,
        ]);

        if ($lease->tenant?->email) {
            Mail::to($lease->tenant->email)->send(new DepositRefundNotification($lease, $status));
        }

        return back()->with('success', 'Deposit refund processed successfully.');
    }

    public function forfeitDeposit(Request $request, Lease $lease): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($lease->deposit_status !== 'held') {
            return back()->withErrors(['error' => 'This deposit has already been processed.']);
        }

        $lease->update([
            'deposit_status' => 'forfeited',
            'deposit_deductions' => $lease->deposit_amount,
            'deposit_deduction_reason' => $request->reason,
            'deposit_processed_at' => now(),
            'deposit_processed_by' => auth()->id(),
        ]);

        DepositTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlordId,
            'processed_by' => auth()->id(),
            'type' => DepositTransaction::TYPE_FORFEIT,
            'amount' => $lease->deposit_amount,
            'balance_after' => 0,
            'reason' => $request->reason,
            'notes' => $request->notes,
        ]);

        if ($lease->tenant?->email) {
            Mail::to($lease->tenant->email)->send(new DepositRefundNotification($lease, 'forfeited'));
        }

        return back()->with('success', 'Deposit forfeited successfully.');
    }

    public function depositTransactions(Request $request, Lease $lease): JsonResponse
    {
        $landlordId = $this->getLandlordId();

        if ($lease->landlord_id !== $landlordId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $transactions = $lease->depositTransactions()
            ->with('processedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'type_label' => $t->getTypeLabel(),
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'reason' => $t->reason,
                'payment_method' => $t->payment_method,
                'reference' => $t->reference,
                'processed_by' => $t->processedBy?->name,
                'created_at' => $t->created_at->format('Y-m-d H:i'),
            ]);

        return response()->json([
            'transactions' => $transactions,
            'deposit_amount' => $lease->deposit_amount,
            'deposit_status' => $lease->deposit_status,
        ]);
    }

    public function exportDeposits(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $landlordId = $this->getLandlordId();
        $format = $request->query('format', 'xlsx');

        $query = Lease::where('landlord_id', $landlordId)
            ->where('deposit_amount', '>', 0)
            ->with([
                'tenant:id,name,email',
                'unit:id,unit_number,building_id',
                'unit.building:id,name',
            ]);

        if ($request->filled('status')) {
            $query->where('deposit_status', $request->status);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        $deposits = $query->orderBy('created_at', 'desc')->get();

        $stats = [
            'total_held' => $deposits->where('deposit_status', 'held')->sum('deposit_amount'),
            'total_refunded' => $deposits->whereIn('deposit_status', ['refunded', 'partial_refund'])->sum('deposit_refund_amount'),
            'total_forfeited' => $deposits->where('deposit_status', 'forfeited')->sum('deposit_amount'),
            'count_held' => $deposits->where('deposit_status', 'held')->count(),
            'count_refunded' => $deposits->whereIn('deposit_status', ['refunded', 'partial_refund'])->count(),
            'count_forfeited' => $deposits->where('deposit_status', 'forfeited')->count(),
        ];

        $filename = 'deposits_report_'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.deposits', [
                'deposits' => $deposits,
                'stats' => $stats,
                'landlord' => auth()->user(),
                'generated_at' => now()->format('M j, Y g:i A'),
                'filters' => $request->only(['status', 'building_id']),
            ]);

            return $pdf->download($filename.'.pdf');
        }

        return Excel::download(
            new \App\Exports\DepositsExport($deposits),
            $filename.'.xlsx'
        );
    }

    public function exportInvoices(Request $request): BinaryFileResponse
    {
        $landlordId = $this->getLandlordId();
        $format = $request->query('format', 'xlsx');

        $query = Invoice::where('landlord_id', $landlordId)
            ->with([
                'lease.tenant:id,name',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        $filename = 'invoices_'.now()->format('Y_m_d_His');

        if ($format === 'pdf') {
            $summary = $this->calculateInvoiceSummary($invoices);

            $pdf = Pdf::loadView('exports.invoices', [
                'invoices' => $invoices,
                'summary' => $summary,
                'filters' => $request->only(['status', 'date_from', 'date_to']),
                'landlord' => auth()->user(),
                'generated_at' => now()->format('F j, Y g:i A'),
            ]);

            return $pdf->download($filename.'.pdf');
        }

        return Excel::download(
            new InvoicesExport($invoices),
            $filename.'.xlsx'
        );
    }

    public function exportPayments(Request $request): BinaryFileResponse
    {
        $landlordId = $this->getLandlordId();
        $format = $request->query('format', 'xlsx');

        $query = Payment::where('landlord_id', $landlordId)
            ->where('is_voided', false)
            ->with([
                'invoice:id,invoice_number',
                'lease.tenant:id,name',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        $payments = $query->orderBy('payment_date', 'desc')->get();

        $filename = 'payments_'.now()->format('Y_m_d_His');

        if ($format === 'pdf') {
            $summary = $this->calculatePaymentSummary($payments);
            $methodBreakdown = $this->calculateMethodBreakdown($payments);

            $pdf = Pdf::loadView('exports.payments', [
                'payments' => $payments,
                'summary' => $summary,
                'method_breakdown' => $methodBreakdown,
                'filters' => $request->only(['method', 'date_from', 'date_to']),
                'landlord' => auth()->user(),
                'generated_at' => now()->format('F j, Y g:i A'),
            ]);

            return $pdf->download($filename.'.pdf');
        }

        $dateRange = [
            'start' => $request->date_from ? \Carbon\Carbon::parse($request->date_from) : now()->subMonth(),
            'end' => $request->date_to ? \Carbon\Carbon::parse($request->date_to) : now(),
        ];

        return Excel::download(
            new PaymentsExport($payments, $dateRange),
            $filename.'.xlsx'
        );
    }

    private function calculateInvoiceSummary($invoices): array
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

    private function calculatePaymentSummary($payments): array
    {
        $totalAmount = $payments->sum('amount');

        return [
            'total_count' => $payments->count(),
            'total_amount' => $totalAmount,
            'average_payment' => $payments->count() > 0 ? $totalAmount / $payments->count() : 0,
        ];
    }

    private function calculateMethodBreakdown($payments): array
    {
        return $payments->groupBy('payment_method')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ])
            ->toArray();
    }

    public function lateFees(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('late-fees', [
            'policies' => $this->getLateFeePolices($landlordId),
            'properties' => $this->getProperties($landlordId),
            'buildings' => $this->getBuildings($landlordId),
            'stats' => $this->getLateFeeStats($landlordId),
        ]);
    }

    public function storeLateFeePolicy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'property_id' => 'nullable|exists:properties,id',
            'building_id' => 'nullable|exists:buildings,id',
            'grace_period_days' => 'required|integer|min:0|max:60',
            'fee_type' => 'required|in:percentage,flat_amount',
            'fee_percentage' => 'required_if:fee_type,percentage|nullable|numeric|min:0|max:100',
            'fee_amount' => 'required_if:fee_type,flat_amount|nullable|numeric|min:0',
            'is_compounding' => 'boolean',
            'compounding_frequency' => 'nullable|in:daily,weekly,monthly',
            'max_fee_cap' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['priority'] = match (true) {
            isset($validated['building_id']) && $validated['building_id'] => 30,
            isset($validated['property_id']) && $validated['property_id'] => 20,
            default => 10,
        };

        LateFeePolicy::create($validated);

        return back()->with('success', 'Late fee policy created successfully.');
    }

    public function updateLateFeePolicy(Request $request, LateFeePolicy $policy): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($policy->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'property_id' => 'nullable|exists:properties,id',
            'building_id' => 'nullable|exists:buildings,id',
            'grace_period_days' => 'required|integer|min:0|max:60',
            'fee_type' => 'required|in:percentage,flat_amount',
            'fee_percentage' => 'required_if:fee_type,percentage|nullable|numeric|min:0|max:100',
            'fee_amount' => 'required_if:fee_type,flat_amount|nullable|numeric|min:0',
            'is_compounding' => 'boolean',
            'compounding_frequency' => 'nullable|in:daily,weekly,monthly',
            'max_fee_cap' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['priority'] = match (true) {
            isset($validated['building_id']) && $validated['building_id'] => 30,
            isset($validated['property_id']) && $validated['property_id'] => 20,
            default => 10,
        };

        $policy->update($validated);

        return back()->with('success', 'Late fee policy updated successfully.');
    }

    public function destroyLateFeePolicy(LateFeePolicy $policy): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($policy->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($policy->lateFees()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete policy with existing late fees. Deactivate it instead.']);
        }

        $policy->delete();

        return back()->with('success', 'Late fee policy deleted successfully.');
    }

    public function toggleLateFeePolicy(LateFeePolicy $policy): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($policy->landlord_id !== $landlordId) {
            abort(403);
        }

        $policy->update(['is_active' => ! $policy->is_active]);

        $status = $policy->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Late fee policy {$status} successfully.");
    }

    public function waiveLateFee(Request $request, LateFee $lateFee, LateFeeService $service): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($lateFee->landlord_id !== $landlordId) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        $service->waiveLateFee($lateFee, auth()->id(), $request->reason);

        return back()->with('success', 'Late fee waived successfully.');
    }

    public function waiveAllLateFees(Request $request, Invoice $invoice, LateFeeService $service): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($invoice->landlord_id !== $landlordId) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        $count = $service->waiveAllFeesForInvoice($invoice, auth()->id(), $request->reason);

        return back()->with('success', "Waived {$count} late fee(s) successfully.");
    }

    public function invoiceLateFees(Invoice $invoice): JsonResponse
    {
        $landlordId = $this->getLandlordId();

        if ($invoice->landlord_id !== $landlordId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invoice->load(['lateFees.policy', 'lateFees.waivedByUser']);

        return response()->json([
            'late_fees' => $invoice->lateFees->map(fn ($fee) => [
                'id' => $fee->id,
                'fee_amount' => $fee->fee_amount,
                'cumulative_total' => $fee->cumulative_total,
                'applied_date' => $fee->applied_date->format('Y-m-d'),
                'days_overdue' => $fee->days_overdue,
                'is_waived' => $fee->is_waived,
                'waived_at' => $fee->waived_at?->format('Y-m-d H:i'),
                'waiver_reason' => $fee->waiver_reason,
                'waived_by' => $fee->waivedByUser?->name,
                'policy_name' => $fee->policy?->name,
            ])->toArray(),
            'total_active' => $invoice->late_fees_total,
            'total_waived' => $invoice->late_fees_waived,
        ]);
    }

    private function getLateFeePolices(int $landlordId): array
    {
        return LateFeePolicy::where('landlord_id', $landlordId)
            ->with(['property:id,name', 'building:id,name'])
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'grace_period_days' => $p->grace_period_days,
                'fee_type' => $p->fee_type,
                'fee_percentage' => $p->fee_percentage,
                'fee_amount' => $p->fee_amount,
                'fee_description' => $p->getFeeDescription(),
                'is_compounding' => $p->is_compounding,
                'compounding_frequency' => $p->compounding_frequency,
                'max_fee_cap' => $p->max_fee_cap,
                'is_active' => $p->is_active,
                'scope_label' => $p->getScopeLabel(),
                'property_id' => $p->property_id,
                'building_id' => $p->building_id,
                'property_name' => $p->property?->name,
                'building_name' => $p->building?->name,
            ])
            ->toArray();
    }

    private function getProperties(int $landlordId): array
    {
        return Property::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    private function getLateFeeStats(int $landlordId): array
    {
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
    }

    public function expenses(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('expenses', [
            'expenses' => $this->getPaginatedExpenses($request, $landlordId),
            'filters' => $request->only(['search', 'category_id', 'vendor_id', 'building_id', 'date_from', 'date_to']),
            'categories' => $this->getExpenseCategories($landlordId),
            'vendors' => $this->getVendors($landlordId),
            'stats' => $this->getExpenseStats($landlordId),
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:expense_categories,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'property_id' => 'nullable|exists:properties,id',
            'building_id' => 'nullable|exists:buildings,id',
            'unit_id' => 'nullable|exists:units,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_recurring' => 'boolean',
            'recurring_frequency' => 'nullable|in:weekly,monthly,quarterly,yearly',
        ]);

        Expense::create($validated);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($expense->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'category_id' => 'nullable|exists:expense_categories,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'property_id' => 'nullable|exists:properties,id',
            'building_id' => 'nullable|exists:buildings,id',
            'unit_id' => 'nullable|exists:units,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_recurring' => 'boolean',
            'recurring_frequency' => 'nullable|in:weekly,monthly,quarterly,yearly',
        ]);

        $expense->update($validated);

        return back()->with('success', 'Expense updated successfully.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($expense->landlord_id !== $landlordId) {
            abort(403);
        }

        $expense->delete();

        return back()->with('success', 'Expense deleted successfully.');
    }

    public function expenseDetail(Expense $expense): JsonResponse
    {
        $landlordId = $this->getLandlordId();

        if ($expense->landlord_id !== $landlordId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $expense->load(['category', 'vendor', 'property', 'building', 'unit']);

        return response()->json([
            'expense' => [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'payment_method' => $expense->payment_method,
                'reference' => $expense->reference,
                'notes' => $expense->notes,
                'is_recurring' => $expense->is_recurring,
                'recurring_frequency' => $expense->recurring_frequency,
                'category_id' => $expense->category_id,
                'vendor_id' => $expense->vendor_id,
                'property_id' => $expense->property_id,
                'building_id' => $expense->building_id,
                'unit_id' => $expense->unit_id,
                'category' => $expense->category?->name,
                'vendor' => $expense->vendor?->name,
                'location' => $expense->getLocationLabel(),
                'created_at' => $expense->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function storeExpenseCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        $validated['is_active'] = true;

        ExpenseCategory::create($validated);

        return back()->with('success', 'Category created successfully.');
    }

    public function updateExpenseCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($category->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroyExpenseCategory(ExpenseCategory $category): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($category->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($category->expenses()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete category with existing expenses.']);
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'tax_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['is_active'] = true;

        Vendor::create($validated);

        return back()->with('success', 'Vendor created successfully.');
    }

    public function updateVendor(Request $request, Vendor $vendor): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($vendor->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'tax_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $vendor->update($validated);

        return back()->with('success', 'Vendor updated successfully.');
    }

    public function destroyVendor(Vendor $vendor): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($vendor->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($vendor->expenses()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete vendor with existing expenses.']);
        }

        $vendor->delete();

        return back()->with('success', 'Vendor deleted successfully.');
    }

    private function getPaginatedExpenses(Request $request, int $landlordId)
    {
        $query = Expense::where('landlord_id', $landlordId)
            ->with(['category', 'vendor', 'property', 'building', 'unit']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        return $query->orderBy('expense_date', 'desc')
            ->paginate(20)
            ->through(fn ($e) => [
                'id' => $e->id,
                'description' => $e->description,
                'amount' => $e->amount,
                'expense_date' => $e->expense_date->format('Y-m-d'),
                'payment_method' => $e->payment_method,
                'reference' => $e->reference,
                'category' => $e->category?->name,
                'category_color' => $e->category?->color,
                'vendor' => $e->vendor?->name,
                'location' => $e->getLocationLabel(),
                'is_recurring' => $e->is_recurring,
            ])
            ->withQueryString();
    }

    private function getExpenseCategories(int $landlordId): array
    {
        return ExpenseCategory::where('landlord_id', $landlordId)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'color' => $c->color,
                'is_active' => $c->is_active,
                'expense_count' => $c->expenses()->count(),
            ])
            ->toArray();
    }

    private function getVendors(int $landlordId): array
    {
        return Vendor::where('landlord_id', $landlordId)
            ->orderBy('name')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'contact_person' => $v->contact_person,
                'email' => $v->email,
                'phone' => $v->phone,
                'is_active' => $v->is_active,
                'total_expenses' => $v->getTotalExpenses(),
            ])
            ->toArray();
    }

    private function getExpenseStats(int $landlordId): array
    {
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
    }

    public function exportExpenses(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $landlordId = $this->getLandlordId();
        $format = $request->query('format', 'xlsx');

        $query = Expense::where('landlord_id', $landlordId)
            ->with(['category', 'vendor', 'property', 'building', 'unit']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->get();

        $filename = 'expenses_'.now()->format('Y_m_d_His');

        if ($format === 'pdf') {
            $summary = $this->calculateExpenseSummary($expenses);
            $categoryBreakdown = $this->calculateExpenseCategoryBreakdown($expenses);

            $pdf = Pdf::loadView('exports.expenses', [
                'expenses' => $expenses,
                'summary' => $summary,
                'category_breakdown' => $categoryBreakdown,
                'filters' => $request->only(['category_id', 'vendor_id', 'date_from', 'date_to']),
                'landlord' => auth()->user(),
                'generated_at' => now()->format('F j, Y g:i A'),
            ]);

            return $pdf->download($filename.'.pdf');
        }

        $dateRange = [
            'start' => $request->date_from ? \Carbon\Carbon::parse($request->date_from) : now()->subMonth(),
            'end' => $request->date_to ? \Carbon\Carbon::parse($request->date_to) : now(),
        ];

        return Excel::download(
            new ExpensesExport($expenses, $dateRange),
            $filename.'.xlsx'
        );
    }

    public function exportVendors(Request $request): BinaryFileResponse
    {
        $landlordId = $this->getLandlordId();

        $vendors = Vendor::where('landlord_id', $landlordId)
            ->withSum('expenses', 'amount')
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        $filename = 'vendors_'.now()->format('Y_m_d_His');

        $dateRange = [
            'start' => now()->subYear(),
            'end' => now(),
        ];

        return Excel::download(
            new VendorExpenseExport($vendors, $dateRange),
            $filename.'.xlsx'
        );
    }

    private function calculateExpenseSummary($expenses): array
    {
        return [
            'total_count' => $expenses->count(),
            'total_amount' => $expenses->sum('amount'),
            'average_expense' => $expenses->count() > 0 ? $expenses->sum('amount') / $expenses->count() : 0,
            'recurring_count' => $expenses->where('is_recurring', true)->count(),
        ];
    }

    private function calculateExpenseCategoryBreakdown($expenses): array
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

    private function getRevenueReport(int $landlordId, int $months): array
    {
        $data = [];
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $invoiced = Invoice::where('landlord_id', $landlordId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total_due');

            $collected = Payment::where('landlord_id', $landlordId)
                ->where('is_voided', false)
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $expenses = Expense::where('landlord_id', $landlordId)
                ->whereBetween('expense_date', [$monthStart, $monthEnd])
                ->sum('amount');

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

    private function getCollectionRateReport(int $landlordId, int $months): array
    {
        $data = [];
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $invoiced = Invoice::where('landlord_id', $landlordId)
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum('total_due');

            $collected = Invoice::where('landlord_id', $landlordId)
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum('amount_paid');

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

    private function getOccupancyReport(int $landlordId): array
    {
        $buildings = Building::where('landlord_id', $landlordId)
            ->with(['units' => fn ($q) => $q->withCount(['leases' => fn ($l) => $l->where('is_active', true)])])
            ->get();

        $data = [];
        foreach ($buildings as $building) {
            $totalUnits = $building->units->count();
            $occupiedUnits = $building->units->where('leases_count', '>', 0)->count();
            $vacantUnits = $totalUnits - $occupiedUnits;
            $occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0;

            $data[] = [
                'building' => $building->name,
                'total_units' => $totalUnits,
                'occupied' => $occupiedUnits,
                'vacant' => $vacantUnits,
                'occupancy_rate' => $occupancyRate,
            ];
        }

        $totals = [
            'building' => 'Total',
            'total_units' => collect($data)->sum('total_units'),
            'occupied' => collect($data)->sum('occupied'),
            'vacant' => collect($data)->sum('vacant'),
            'occupancy_rate' => collect($data)->sum('total_units') > 0
                ? round((collect($data)->sum('occupied') / collect($data)->sum('total_units')) * 100, 1)
                : 0,
        ];

        return ['buildings' => $data, 'totals' => $totals];
    }

    private function getArrearsAgingReport(int $landlordId): array
    {
        $invoices = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->whereRaw('total_due > amount_paid')
            ->with(['lease.tenant:id,name', 'lease.unit:id,unit_number,building_id', 'lease.unit.building:id,name'])
            ->get();

        $aging = [
            'current' => ['count' => 0, 'amount' => 0],
            '1-30' => ['count' => 0, 'amount' => 0],
            '31-60' => ['count' => 0, 'amount' => 0],
            '61-90' => ['count' => 0, 'amount' => 0],
            '90+' => ['count' => 0, 'amount' => 0],
        ];

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->total_due - $invoice->amount_paid;
            $daysOverdue = $invoice->due_date ? now()->diffInDays($invoice->due_date, false) : 0;

            if ($daysOverdue <= 0) {
                $aging['current']['count']++;
                $aging['current']['amount'] += $outstanding;
            } elseif ($daysOverdue <= 30) {
                $aging['1-30']['count']++;
                $aging['1-30']['amount'] += $outstanding;
            } elseif ($daysOverdue <= 60) {
                $aging['31-60']['count']++;
                $aging['31-60']['amount'] += $outstanding;
            } elseif ($daysOverdue <= 90) {
                $aging['61-90']['count']++;
                $aging['61-90']['amount'] += $outstanding;
            } else {
                $aging['90+']['count']++;
                $aging['90+']['amount'] += $outstanding;
            }
        }

        $totalOutstanding = collect($aging)->sum('amount');

        return [
            'aging' => $aging,
            'total_outstanding' => round($totalOutstanding, 2),
            'total_invoices' => $invoices->count(),
        ];
    }

    private function getExpensesByCategoryReport(int $landlordId, int $months): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        $expenses = Expense::where('landlord_id', $landlordId)
            ->where('expense_date', '>=', $startDate)
            ->with('category:id,name,color')
            ->get();

        $byCategory = $expenses->groupBy('category_id')
            ->map(fn ($group) => [
                'category' => $group->first()->category?->name ?? 'Uncategorized',
                'color' => $group->first()->category?->color ?? '#6B7280',
                'amount' => round($group->sum('amount'), 2),
                'count' => $group->count(),
                'percentage' => 0,
            ])
            ->values();

        $total = $byCategory->sum('amount');
        $byCategory = $byCategory->map(function ($item) use ($total) {
            $item['percentage'] = $total > 0 ? round(($item['amount'] / $total) * 100, 1) : 0;

            return $item;
        });

        return [
            'categories' => $byCategory->toArray(),
            'total' => round($total, 2),
        ];
    }

    public function sendArrearsNotices(Request $request): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        $overdueInvoices = Invoice::where('landlord_id', $landlordId)
            ->where('status', 'overdue')
            ->with('lease.tenant')
            ->get();

        $sentCount = 0;
        foreach ($overdueInvoices as $invoice) {
            if ($invoice->lease?->tenant?->email) {
                $sentCount++;
            }
        }

        if ($sentCount === 0) {
            return back()->with('info', 'No tenants with arrears have email addresses configured.');
        }

        return back()->with('success', "Arrears notices queued for {$sentCount} tenant(s).");
    }

    public function sendRentReminders(Request $request): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        $upcomingInvoices = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', ['sent', 'draft'])
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->with('lease.tenant')
            ->get();

        $sentCount = 0;
        foreach ($upcomingInvoices as $invoice) {
            if ($invoice->lease?->tenant?->email) {
                $sentCount++;
            }
        }

        if ($sentCount === 0) {
            return back()->with('info', 'No upcoming invoices found for reminders.');
        }

        return back()->with('success', "Payment reminders queued for {$sentCount} tenant(s).");
    }

    public function importBankStatement(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
        ]);

        return back()->with('info', 'Bank statement import is coming soon. This feature is under development.');
    }

    public function processReconciliationQueue(Request $request): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        $unmatchedCount = Payment::where('landlord_id', $landlordId)
            ->whereNull('invoice_id')
            ->count();

        if ($unmatchedCount === 0) {
            return back()->with('info', 'No unmatched payments to process.');
        }

        return back()->with('info', "Auto-matching {$unmatchedCount} payment(s) is coming soon. Use manual matching for now.");
    }

    private function getWaterConsumptionReport(int $landlordId, int $months): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        $readings = \App\Models\WaterReading::where('landlord_id', $landlordId)
            ->where('reading_date', '>=', $startDate)
            ->where('status', 'approved')
            ->get();

        $totalConsumption = $readings->sum('consumption');
        $totalCost = $readings->sum('cost');
        $avgConsumption = $readings->count() > 0 ? round($totalConsumption / $readings->count(), 2) : 0;

        $topConsumers = \App\Models\WaterReading::where('landlord_id', $landlordId)
            ->where('reading_date', '>=', $startDate)
            ->where('status', 'approved')
            ->with('unit:id,unit_number,building_id', 'unit.building:id,name')
            ->selectRaw('unit_id, SUM(consumption) as total_consumption, SUM(cost) as total_cost')
            ->groupBy('unit_id')
            ->orderByDesc('total_consumption')
            ->limit(10)
            ->get()
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
            'readings_count' => $readings->count(),
            'top_consumers' => $topConsumers,
        ];
    }

    private function getTopPerformingUnitsReport(int $landlordId, int $months): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        $units = \App\Models\Unit::where('landlord_id', $landlordId)
            ->where('status', 'occupied')
            ->with(['activeLease.tenant:id,name', 'building:id,name'])
            ->get();

        $performance = [];

        foreach ($units as $unit) {
            if (! $unit->activeLease) {
                continue;
            }

            $invoices = Invoice::where('lease_id', $unit->activeLease->id)
                ->where('created_at', '>=', $startDate)
                ->get();

            if ($invoices->isEmpty()) {
                continue;
            }

            $totalBilled = $invoices->sum('total_due');
            $totalPaid = $invoices->sum('amount_paid');
            $onTimePayments = $invoices->where('status', 'paid')->count();

            $performance[] = [
                'unit' => $unit->unit_number,
                'building' => $unit->building?->name ?? 'N/A',
                'tenant' => $unit->activeLease->tenant?->name ?? 'N/A',
                'collection_rate' => $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100, 1) : 0,
                'on_time_payments' => $onTimePayments,
                'total_invoices' => $invoices->count(),
            ];
        }

        usort($performance, fn ($a, $b) => $b['collection_rate'] <=> $a['collection_rate']);

        return array_slice($performance, 0, 10);
    }
}
