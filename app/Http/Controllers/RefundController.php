<?php

namespace App\Http\Controllers;

use App\Http\Requests\RefundRequest;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Refund::class);

        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Refund::where('landlord_id', $landlordId)
            ->with(['payment', 'invoice', 'initiator'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $refunds = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => Refund::where('landlord_id', $landlordId)->count(),
            'pending' => Refund::where('landlord_id', $landlordId)->pending()->count(),
            'completed' => Refund::where('landlord_id', $landlordId)->completed()->count(),
            'total_refunded' => Refund::where('landlord_id', $landlordId)->completed()->sum('amount'),
        ];

        return Inertia::render('Refunds/Index', [
            'refunds' => $refunds,
            'stats' => $stats,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(Payment $payment)
    {
        $this->authorize('create', Refund::class);
        $this->authorize('view', $payment);

        $payment->load(['invoice', 'lease.tenant', 'lease.unit.building']);

        $refundableAmount = $this->refundService->getRefundableAmount($payment);

        return Inertia::render('Refunds/Create', [
            'payment' => $payment,
            'refundableAmount' => $refundableAmount,
        ]);
    }

    public function store(Request $request, Payment $payment)
    {
        $this->authorize('create', Refund::class);
        $this->authorize('view', $payment);

        $user = auth()->user();
        $refundableAmount = $this->refundService->getRefundableAmount($payment);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$refundableAmount}"],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $refund = $this->refundService->initiateRefund(
                $payment,
                $request->amount,
                $request->reason,
                $user->id
            );

            return redirect()->route('refunds.index')
                ->with('success', 'Refund request created successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    public function show(Refund $refund)
    {
        $this->authorize('view', $refund);

        $refund->load(['payment', 'invoice', 'initiator', 'approver']);

        return Inertia::render('Refunds/Show', [
            'refund' => $refund,
        ]);
    }

    public function process(Refund $refund)
    {
        $this->authorize('process', $refund);

        if (! $refund->canProcess()) {
            return back()->withErrors(['refund' => 'This refund cannot be processed.']);
        }

        try {
            $success = $this->refundService->processRefund($refund);

            if ($success) {
                return back()->with('success', 'Refund processed successfully.');
            }

            return back()->withErrors(['refund' => 'Refund processing failed. Please try again.']);
        } catch (\Exception $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }
    }

    public function cancel(Refund $refund)
    {
        $this->authorize('cancel', $refund);

        if (! $refund->isPending()) {
            return back()->withErrors(['refund' => 'Only pending refunds can be cancelled.']);
        }

        $this->refundService->cancelRefund($refund);

        return back()->with('success', 'Refund cancelled successfully.');
    }

    public function createStandalone()
    {
        $this->authorize('create', Refund::class);

        $refundMethods = [
            ['value' => 'original_method', 'label' => 'Original Payment Method'],
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money (M-Pesa)'],
        ];

        $refundReasons = [
            ['value' => 'Overpayment', 'label' => 'Overpayment'],
            ['value' => 'Duplicate Payment', 'label' => 'Duplicate Payment'],
            ['value' => 'Service Not Rendered', 'label' => 'Service Not Rendered'],
            ['value' => 'Tenant Request', 'label' => 'Tenant Request'],
            ['value' => 'Deposit Refund', 'label' => 'Deposit Refund'],
            ['value' => 'Other', 'label' => 'Other'],
        ];

        return Inertia::render('Finances/Refunds/Create', [
            'refundMethods' => $refundMethods,
            'refundReasons' => $refundReasons,
        ]);
    }

    public function storeStandalone(RefundRequest $request)
    {
        $this->authorize('create', Refund::class);

        $payment = Payment::findOrFail($request->payment_id);
        // SCOPE-P4: storeStandalone previously had no payment-ownership
        // check — a tenant or caretaker could refund any payment by id.
        // Routing through PaymentPolicy::view closes that IDOR.
        $this->authorize('view', $payment);

        $user = auth()->user();

        try {
            $refund = $this->refundService->initiateRefund(
                $payment,
                $request->amount,
                $request->reason,
                $user->id
            );

            $refund->update([
                'payment_method' => $request->refund_method === 'original_method'
                    ? $payment->payment_method
                    : $request->refund_method,
                'notes' => $request->notes,
            ]);

            return redirect()->route('finances.refunds')
                ->with('success', 'Refund request created successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }
}
