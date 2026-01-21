<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\Finance\MatchPaymentRequest;
use App\Http\Traits\WithETag;
use App\Http\Traits\WithFinanceRendering;
use App\Http\Traits\WithLandlordScope;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceExportService;
use App\Services\FinanceFilterService;
use App\Services\FinanceStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancesController extends Controller
{
    use WithETag;
    use WithFinanceRendering;
    use WithLandlordScope;

    public function __construct(
        protected FinanceStatsService $statsService,
        protected FinanceFilterService $filterService,
        protected FinanceExportService $exportService,
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
        $stats = $this->statsService->getOverviewStats($landlordId);

        return $this->renderFinances('overview', [
            'stats' => $stats,
            'recentPayments' => $this->statsService->getRecentPayments($landlordId, 5),
            'recentInvoices' => $this->statsService->getRecentInvoices($landlordId, 5),
            'collectionStatus' => $this->statsService->getCollectionStatus($landlordId, $stats['collection_rate']),
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

    public function arrears(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('arrears', [
            'arrears' => $this->filterService->getArrearsData($request, $landlordId),
            'filters' => $request->only(['search', 'building_id', 'min_days', 'max_amount']),
            'stats' => $this->statsService->getArrearsStats($landlordId),
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

        return $this->jsonWithCache([
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
        ], 60, 300);
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

        return $this->jsonWithCache([
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
        ], 60, 300);
    }

    public function matchPayment(MatchPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($payment->landlord_id !== $landlordId) {
            abort(403);
        }

        $invoice = Invoice::where('landlord_id', $landlordId)
            ->where('id', $request->invoice_id)
            ->firstOrFail();

        $payment->invoice_id = $invoice->id;
        $payment->save();

        $invoice->amount_paid += $payment->amount;
        if ($invoice->amount_paid >= $invoice->total_due) {
            $invoice->status = InvoiceStatus::Paid;
        } elseif ($invoice->amount_paid > 0) {
            $invoice->status = InvoiceStatus::Partial;
        }
        $invoice->save();

        return back()->with('success', 'Payment matched to invoice successfully.');
    }

    public function exportInvoices(Request $request): BinaryFileResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = array_merge(
            ['landlord_id' => $this->getLandlordId()],
            $request->only(['status', 'building_id', 'date_from', 'date_to'])
        );

        return $this->exportService->exportInvoices($filters, $request->query('format', 'xlsx'));
    }

    public function exportPayments(Request $request): BinaryFileResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = array_merge(
            ['landlord_id' => $this->getLandlordId()],
            $request->only(['method', 'building_id', 'date_from', 'date_to'])
        );

        return $this->exportService->exportPayments($filters, $request->query('format', 'xlsx'));
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
}
