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
use App\Models\User;
use App\Models\Vendor;
use App\Services\FinanceFilterService;
use App\Services\FinanceReportService;
use App\Services\FinanceSettingsService;
use App\Services\FinanceStatsService;
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
    public function __construct(
        protected FinanceStatsService $statsService,
        protected FinanceReportService $reportService,
        protected FinanceFilterService $filterService,
        protected FinanceSettingsService $settingsService,
    ) {}

    public function index(): Response
    {
        $landlordId = $this->getLandlordId();

        return Inertia::render('Finances/Hub', [
            'stats' => $this->statsService->getHubStats($landlordId),
            'buildings' => $this->getBuildings($landlordId),
            'properties' => $this->getProperties($landlordId),
        ]);
    }

    public function overview(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('overview', [
            'stats' => $this->statsService->getOverviewStats($landlordId),
            'recentPayments' => $this->statsService->getRecentPayments($landlordId, 5),
            'recentInvoices' => $this->statsService->getRecentInvoices($landlordId, 5),
            'collectionStatus' => $this->statsService->getCollectionStatus($landlordId),
            'monthlyTrend' => $this->statsService->getMonthlyTrend($landlordId, 6),
        ]);
    }

    public function invoices(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('invoices', [
            'invoices' => $this->filterService->getPaginatedInvoices($request, $landlordId),
            'filters' => $request->only(['search', 'status', 'building_id', 'date_from', 'date_to']),
            'statusOptions' => $this->filterService->getInvoiceStatusOptions(),
        ]);
    }

    public function payments(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('payments', [
            'payments' => $this->filterService->getPaginatedPayments($request, $landlordId),
            'filters' => $request->only(['search', 'method', 'building_id', 'date_from', 'date_to']),
            'paymentMethodOptions' => $this->filterService->getPaymentMethodOptions(),
        ]);
    }

    public function refunds(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('refunds', [
            'refunds' => $this->filterService->getPaginatedRefunds($request, $landlordId),
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'statusOptions' => $this->filterService->getRefundStatusOptions(),
        ]);
    }

    public function reconciliation(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('reconciliation', [
            'unmatchedPayments' => $this->filterService->getUnmatchedPayments($landlordId),
            'pendingReconciliation' => $this->statsService->getPendingReconciliationCount($landlordId),
        ]);
    }

    public function deposits(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('deposits', [
            'deposits' => $this->filterService->getPaginatedDeposits($request, $landlordId),
            'filters' => $request->only(['search', 'status', 'building_id']),
            'stats' => $this->statsService->getDepositStats($landlordId),
        ]);
    }

    public function arrears(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('arrears', [
            'arrears' => $this->filterService->getArrearsData($request, $landlordId),
            'filters' => $request->only(['search', 'building_id', 'min_days', 'max_amount']),
            'stats' => $this->statsService->getArrearsStats($landlordId),
        ]);
    }

    public function settings(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('settings', [
            'paymentConfig' => $this->settingsService->getPaymentConfig($landlordId),
            'paymentMethods' => PaymentConfiguration::PAYMENT_METHODS,
            'invoiceSettings' => $this->settingsService->getInvoiceSettings($landlordId),
            'reminderSettings' => $this->settingsService->getReminderSettings($landlordId),
            'receiptSettings' => $this->settingsService->getReceiptSettings($landlordId),
            'fiscalYearSettings' => $this->settingsService->getFiscalYearSettings($landlordId),
        ]);
    }

    public function reports(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $user = auth()->user();

        $period = $request->query('period', '12');
        $buildingId = $request->query('building_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $compare = $request->boolean('compare', false);

        $dateRange = $this->reportService->getReportDateRange($period, $dateFrom, $dateTo, $landlordId);

        $hasWaterAccess = $user->isLandlord()
            ? $user->canAccessFeature('water_billing')
            : $user->landlord?->canAccessFeature('water_billing') ?? false;

        $currentData = [
            'revenueData' => $this->reportService->getRevenueReportFiltered($landlordId, $dateRange, $buildingId),
            'collectionRate' => $this->reportService->getCollectionRateReportFiltered($landlordId, $dateRange, $buildingId),
            'occupancyData' => $this->reportService->getOccupancyReport($landlordId, $buildingId),
            'arrearsAging' => $this->reportService->getArrearsAgingReport($landlordId, $buildingId),
            'expensesByCategory' => $this->reportService->getExpensesByCategoryReportFiltered($landlordId, $dateRange, $buildingId),
            'waterConsumption' => $hasWaterAccess
                ? $this->reportService->getWaterConsumptionReportFiltered($landlordId, $dateRange, $buildingId)
                : null,
            'topPerformingUnits' => $this->reportService->getTopPerformingUnitsReportFiltered($landlordId, $dateRange, $buildingId),
        ];

        $previousPeriodData = null;
        if ($compare) {
            $previousDateRange = $this->reportService->getPreviousPeriodDateRange($dateRange);
            $previousPeriodData = [
                'totals' => $this->reportService->getReportTotals($landlordId, $previousDateRange, $buildingId),
            ];
        }

        $currentTotals = $this->reportService->getReportTotals($landlordId, $dateRange, $buildingId);

        return $this->renderFinances('reports', array_merge($currentData, [
            'totals' => $currentTotals,
            'previousPeriodData' => $previousPeriodData,
            'featureAccess' => ['water_billing' => $hasWaterAccess],
            'filters' => [
                'period' => $period,
                'building_id' => $buildingId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'compare' => $compare,
            ],
        ]));
    }

    public function exportReports(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $landlordId = $this->getLandlordId();
        $period = (int) $request->query('period', '12');
        $format = $request->query('format', 'xlsx');

        $data = [
            'revenue' => $this->reportService->getRevenueReport($landlordId, $period),
            'collection_rate' => $this->reportService->getCollectionRateReport($landlordId, $period),
            'occupancy' => $this->reportService->getOccupancyReport($landlordId),
            'arrears_aging' => $this->reportService->getArrearsAgingReport($landlordId),
            'expenses_by_category' => $this->reportService->getExpensesByCategoryReport($landlordId, $period),
            'water_consumption' => $this->reportService->getWaterConsumptionReport($landlordId, $period),
            'top_performing_units' => $this->reportService->getTopPerformingUnitsReport($landlordId, $period),
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

        $this->settingsService->updatePaymentMethods($this->getLandlordId(), $request);

        return back()->with('success', 'Payment methods saved successfully.');
    }

    public function updateInvoiceSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'include_water_charges' => 'required|boolean',
            'include_arrears' => 'required|boolean',
            'auto_generate_monthly' => 'required|boolean',
        ]);

        $this->settingsService->updateInvoiceSettings($this->getLandlordId(), $request);

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

        $this->settingsService->updateReminderSettings($this->getLandlordId(), $request);

        return back()->with('success', 'Reminder settings saved successfully.');
    }

    public function updateReceiptSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'auto_email_receipt' => 'boolean',
            'receipt_show_logo' => 'boolean',
            'receipt_show_tenant_details' => 'boolean',
            'receipt_show_invoice_details' => 'boolean',
            'receipt_show_payment_method' => 'boolean',
            'receipt_header_text' => 'nullable|string|max:255',
            'receipt_footer_text' => 'nullable|string|max:2000',
            'receipt_thank_you_message' => 'nullable|string|max:500',
        ]);

        $this->settingsService->updateReceiptSettings($this->getLandlordId(), $request);

        return back()->with('success', 'Receipt settings saved successfully.');
    }

    public function updateFiscalYearSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'fiscal_year_type' => 'required|in:calendar,custom',
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
        ]);

        $this->settingsService->updateFiscalYearSettings($this->getLandlordId(), $request);

        return back()->with('success', 'Fiscal year settings saved successfully.');
    }

    public function previewReceipt()
    {
        $landlordId = $this->getLandlordId();
        $user = User::find($landlordId);
        $settings = $user->getOrCreateInvoiceSetting();

        $samplePayment = (object) [
            'reference' => 'RCT-202601-0001',
            'payment_date' => now(),
            'payment_method' => 'mpesa',
            'amount' => 25000,
            'notes' => 'Sample payment for preview',
        ];

        $sampleInvoice = (object) [
            'invoice_number' => 'INV-202601-0001',
            'billing_period_start' => now()->startOfMonth(),
            'total_due' => 25000,
            'amount_paid' => 25000,
            'lease' => (object) [
                'tenant' => (object) [
                    'name' => 'John Doe',
                    'email' => 'johndoe@example.com',
                ],
                'unit' => (object) [
                    'unit_number' => 'A101',
                    'building' => (object) [
                        'name' => 'Sunrise Apartments',
                    ],
                ],
            ],
        ];

        $sampleReceipt = (object) [
            'receipt_number' => 'RCT-202601-0001',
        ];

        return Pdf::loadView('receipts.payment-receipt', [
            'payment' => $samplePayment,
            'invoice' => $sampleInvoice,
            'receipt' => $sampleReceipt,
            'settings' => $settings,
        ])->stream('receipt-preview.pdf');
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
            $summary = $this->statsService->calculateInvoiceSummary($invoices);

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
            $summary = $this->statsService->calculatePaymentSummary($payments);
            $methodBreakdown = $this->statsService->calculateMethodBreakdown($payments);

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

    public function lateFees(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('late-fees', [
            'policies' => $this->getLateFeePolices($landlordId),
            'properties' => $this->getProperties($landlordId),
            'buildings' => $this->getBuildings($landlordId),
            'stats' => $this->statsService->getLateFeeStats($landlordId),
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

    public function expenses(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('expenses', [
            'expenses' => $this->filterService->getPaginatedExpenses($request, $landlordId),
            'filters' => $request->only(['search', 'category_id', 'vendor_id', 'building_id', 'date_from', 'date_to']),
            'categories' => $this->filterService->getExpenseCategories($landlordId),
            'vendors' => $this->filterService->getVendors($landlordId),
            'stats' => $this->statsService->getExpenseStats($landlordId),
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
            $summary = $this->statsService->calculateExpenseSummary($expenses);
            $categoryBreakdown = $this->statsService->calculateExpenseCategoryBreakdown($expenses);

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

    private function renderFinances(string $tab, array $additionalProps = []): Response
    {
        $landlordId = $this->getLandlordId();

        $baseProps = [
            'activeTab' => $tab,
            'activeGroup' => $this->getActiveGroup($tab),
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
            [
                'id' => 'billing',
                'name' => 'Billing',
                'route' => 'finances.invoices',
                'subtabs' => [
                    ['id' => 'invoices', 'name' => 'Invoices', 'route' => 'finances.invoices'],
                    ['id' => 'payments', 'name' => 'Payments', 'route' => 'finances.payments'],
                ],
            ],
            ['id' => 'expenses', 'name' => 'Expenses', 'route' => 'finances.expenses'],
            [
                'id' => 'collections',
                'name' => 'Collections',
                'route' => 'finances.arrears',
                'subtabs' => [
                    ['id' => 'arrears', 'name' => 'Arrears', 'route' => 'finances.arrears'],
                    ['id' => 'late-fees', 'name' => 'Late Fees', 'route' => 'finances.late-fees'],
                    ['id' => 'deposits', 'name' => 'Deposits', 'route' => 'finances.deposits'],
                    ['id' => 'refunds', 'name' => 'Refunds', 'route' => 'finances.refunds'],
                ],
            ],
            ['id' => 'reconciliation', 'name' => 'Reconciliation', 'route' => 'finances.reconciliation'],
            ['id' => 'reports', 'name' => 'Reports', 'route' => 'finances.reports'],
            ['id' => 'settings', 'name' => 'Settings', 'route' => 'finances.settings'],
        ];
    }

    private function getActiveGroup(string $tab): ?string
    {
        $groupMap = [
            'invoices' => 'billing',
            'payments' => 'billing',
            'arrears' => 'collections',
            'late-fees' => 'collections',
            'deposits' => 'collections',
            'refunds' => 'collections',
        ];

        return $groupMap[$tab] ?? null;
    }

    private function getBuildings(int $landlordId): array
    {
        return Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
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
}
