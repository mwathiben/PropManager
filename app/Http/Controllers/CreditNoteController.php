<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CreditNoteService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditNoteController extends Controller
{
    public function __construct(
        protected CreditNoteService $creditNoteService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = CreditNote::where('landlord_id', $landlordId)
            ->with(['tenant', 'lease.unit.building', 'invoice', 'appliedToInvoice'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('credit_number', 'like', "%{$search}%")
                    ->orWhereHas('tenant', function ($tq) use ($search) {
                        $tq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $creditNotes = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => CreditNote::where('landlord_id', $landlordId)->count(),
            'pending' => CreditNote::where('landlord_id', $landlordId)->where('status', 'pending')->count(),
            'approved' => CreditNote::where('landlord_id', $landlordId)->where('status', 'approved')->count(),
            'applied' => CreditNote::where('landlord_id', $landlordId)->where('status', 'applied')->count(),
            'total_amount' => CreditNote::where('landlord_id', $landlordId)
                ->whereIn('status', ['approved', 'applied'])
                ->sum('amount'),
        ];

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes,
            'stats' => $stats,
            'filters' => $request->only(['status', 'search']),
            'reasonOptions' => CreditNote::getReasonOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403);
        }

        return Inertia::render('CreditNotes/Create', [
            'reasonOptions' => CreditNote::getReasonOptions(),
            'tenantId' => $request->tenant_id,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403);
        }

        $request->validate([
            'tenant_id' => ['required', 'exists:users,id'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'reason' => ['required', 'string', 'in:'.implode(',', array_keys(CreditNote::getReasonOptions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant = User::findOrFail($request->tenant_id);
        $lease = $tenant->leases()->where('is_active', true)->first();

        if (! $lease || $lease->landlord_id !== $landlordId) {
            return back()->withErrors(['tenant_id' => 'Invalid tenant selected.']);
        }

        if ($request->invoice_id) {
            $invoice = Invoice::findOrFail($request->invoice_id);
            if ($invoice->landlord_id !== $landlordId) {
                return back()->withErrors(['invoice_id' => 'Invalid invoice selected.']);
            }
        }

        $creditNote = CreditNote::create([
            'landlord_id' => $landlordId,
            'lease_id' => $lease->id,
            'tenant_id' => $tenant->id,
            'invoice_id' => $request->invoice_id,
            'credit_number' => CreditNote::generateCreditNumber(User::find($landlordId)),
            'amount' => $request->amount,
            'reason' => $request->reason,
            'notes' => $request->notes,
            'status' => CreditNote::STATUS_PENDING,
        ]);

        return redirect()->route('credit-notes.show', $creditNote)
            ->with('success', 'Credit note created successfully. Awaiting approval.');
    }

    public function show(CreditNote $creditNote)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($creditNote->landlord_id !== $landlordId) {
            abort(403);
        }

        $creditNote->load(['tenant', 'lease.unit.building', 'invoice', 'appliedToInvoice', 'approvedByUser']);

        $outstandingInvoices = [];
        if ($creditNote->canBeApplied()) {
            $outstandingInvoices = Invoice::where('lease_id', $creditNote->lease_id)
                ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
                ->orderBy('due_date')
                ->get()
                ->map(fn ($inv) => [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'total_due' => $inv->total_due,
                    'amount_paid' => $inv->amount_paid,
                    'outstanding' => $inv->getOutstandingAmount(),
                    'due_date' => $inv->due_date?->format('Y-m-d'),
                ]);
        }

        return Inertia::render('CreditNotes/Show', [
            'creditNote' => $creditNote,
            'reasonOptions' => CreditNote::getReasonOptions(),
            'outstandingInvoices' => $outstandingInvoices,
        ]);
    }

    public function approve(CreditNote $creditNote)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($creditNote->landlord_id !== $landlordId) {
            abort(403);
        }

        if (! $creditNote->canBeApproved()) {
            return back()->withErrors(['credit_note' => 'This credit note cannot be approved.']);
        }

        $creditNote->approve($user);

        $this->creditNoteService->sendApprovalNotification($creditNote);

        return back()->with('success', 'Credit note approved successfully. Tenant has been notified.');
    }

    public function downloadPdf(CreditNote $creditNote)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($creditNote->landlord_id !== $landlordId) {
            abort(403);
        }

        return $this->creditNoteService->downloadPdf($creditNote);
    }

    public function apply(Request $request, CreditNote $creditNote)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($creditNote->landlord_id !== $landlordId) {
            abort(403);
        }

        if (! $creditNote->canBeApplied()) {
            return back()->withErrors(['credit_note' => 'This credit note cannot be applied.']);
        }

        $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        if ($invoice->landlord_id !== $landlordId) {
            abort(403);
        }

        $amountApplied = $creditNote->applyToInvoice($invoice, $request->amount);

        if ($amountApplied <= 0) {
            return back()->withErrors(['amount' => 'No amount could be applied to this invoice.']);
        }

        return back()->with('success', 'KES '.number_format($amountApplied, 2)." credit applied to invoice {$invoice->invoice_number}.");
    }

    public function void(CreditNote $creditNote)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($creditNote->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($creditNote->isApplied()) {
            return back()->withErrors(['credit_note' => 'Applied credit notes cannot be voided.']);
        }

        $creditNote->void();

        return back()->with('success', 'Credit note voided successfully.');
    }

    public function forTenant(User $tenant)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $lease = $tenant->leases()->where('is_active', true)->first();

        if (! $lease || $lease->landlord_id !== $landlordId) {
            return response()->json([]);
        }

        $creditNotes = CreditNote::where('tenant_id', $tenant->id)
            ->where('landlord_id', $landlordId)
            ->whereIn('status', ['approved', 'applied'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($cn) => [
                'id' => $cn->id,
                'credit_number' => $cn->credit_number,
                'amount' => $cn->amount,
                'applied_amount' => $cn->applied_amount,
                'remaining_amount' => $cn->remaining_amount,
                'status' => $cn->status,
                'reason' => $cn->reason_label,
                'created_at' => $cn->created_at->format('Y-m-d'),
            ]);

        return response()->json($creditNotes);
    }
}
