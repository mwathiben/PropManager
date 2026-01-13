<?php

namespace App\Http\Controllers;

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
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403);
        }

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
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($payment->landlord_id !== $landlordId) {
            abort(403);
        }

        $payment->load(['invoice', 'lease.tenant', 'lease.unit.building']);

        $refundableAmount = $this->refundService->getRefundableAmount($payment);

        return Inertia::render('Refunds/Create', [
            'payment' => $payment,
            'refundableAmount' => $refundableAmount,
        ]);
    }

    public function store(Request $request, Payment $payment)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($payment->landlord_id !== $landlordId) {
            abort(403);
        }

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
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($refund->landlord_id !== $landlordId) {
            abort(403);
        }

        $refund->load(['payment', 'invoice', 'initiator', 'approver']);

        return Inertia::render('Refunds/Show', [
            'refund' => $refund,
        ]);
    }

    public function process(Refund $refund)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($refund->landlord_id !== $landlordId) {
            abort(403);
        }

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
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($refund->landlord_id !== $landlordId) {
            abort(403);
        }

        if (! $refund->isPending()) {
            return back()->withErrors(['refund' => 'Only pending refunds can be cancelled.']);
        }

        $this->refundService->cancelRefund($refund);

        return back()->with('success', 'Refund cancelled successfully.');
    }

    public function createStandalone()
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403);
        }

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

    public function storeStandalone(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403);
        }

        $request->validate([
            'payment_id' => ['required', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'refund_method' => ['required', 'string', 'in:original_method,cash,bank_transfer,mobile_money'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = Payment::findOrFail($request->payment_id);

        if ($payment->landlord_id !== $landlordId) {
            abort(403);
        }

        $refundableAmount = $this->refundService->getRefundableAmount($payment);

        if ($request->amount > $refundableAmount) {
            return back()->withErrors([
                'amount' => "Amount cannot exceed the refundable amount of {$refundableAmount}.",
            ]);
        }

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
