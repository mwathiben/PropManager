<?php

namespace App\Http\Controllers;

use App\Mail\OverpaymentNotification;
use App\Mail\PaymentReceived;
use App\Mail\PaymentVerificationApproved;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\LandlordPayoutAccount;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\TenantPaymentVerification;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingModelService;
use App\Services\PaystackService;
use App\Services\ReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PaymentController extends Controller
{
    protected PaystackService $paystackService;

    protected BillingModelService $billingService;

    protected ReceiptService $receiptService;

    public function __construct(
        PaystackService $paystackService,
        BillingModelService $billingService,
        ReceiptService $receiptService
    ) {
        $this->paystackService = $paystackService;
        $this->billingService = $billingService;
        $this->receiptService = $receiptService;
    }

    /**
     * Show the record payment form.
     */
    public function create(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        // Get enabled payment methods from settings
        $settings = \App\Models\Setting::where('user_id', $landlordId)
            ->where('key', 'accepted_payment_methods')
            ->first();

        $enabledMethods = $settings ? json_decode($settings->value, true) : ['cash', 'bank_transfer', 'mobile_money'];

        $paymentMethods = collect([
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money'],
            ['value' => 'mpesa', 'label' => 'M-Pesa'],
            ['value' => 'cheque', 'label' => 'Cheque'],
        ])->filter(fn ($m) => in_array($m['value'], $enabledMethods) || empty($enabledMethods))->values();

        // Get buildings for filtering
        $buildings = \App\Models\Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Finances/Payments/Record', [
            'paymentMethods' => $paymentMethods,
            'buildings' => $buildings,
        ]);
    }

    /**
     * Store a manually recorded payment.
     */
    public function storeManual(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $validated = $request->validate([
            'tenant_id' => 'required_without:invoice_id|nullable|exists:users,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,mpesa,cheque',
            'payment_date' => 'required|date|before_or_equal:today',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'is_unallocated' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $invoice = null;
            $lease = null;
            $appliedAmount = $validated['amount'];
            $overpayment = 0;

            if ($validated['invoice_id'] && ! ($validated['is_unallocated'] ?? false)) {
                $invoice = Invoice::where('id', $validated['invoice_id'])
                    ->where('landlord_id', $landlordId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lease = $invoice->lease;

                $remainingBalance = $invoice->total_due - $invoice->amount_paid;
                $appliedAmount = min($validated['amount'], $remainingBalance);
                $overpayment = max(0, $validated['amount'] - $remainingBalance);
            } elseif ($validated['tenant_id']) {
                $tenant = User::where('id', $validated['tenant_id'])
                    ->where('landlord_id', $landlordId)
                    ->firstOrFail();

                $lease = $tenant->leases()->where('is_active', true)->first();
            }

            $payment = Payment::create([
                'invoice_id' => $invoice?->id,
                'lease_id' => $lease?->id,
                'landlord_id' => $landlordId,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'],
                'reference' => $validated['reference'] ?? 'MANUAL-'.strtoupper(uniqid()),
                'notes' => $validated['notes'],
            ]);

            if ($invoice) {
                $newAmountPaid = $invoice->amount_paid + $appliedAmount;
                $newStatus = $newAmountPaid >= $invoice->total_due ? 'paid' : 'partial';

                $invoice->update([
                    'amount_paid' => $newAmountPaid,
                    'status' => $newStatus,
                ]);

                if ($overpayment > 0 && $lease) {
                    $lease->creditToWallet(
                        $overpayment,
                        "Overpayment from manual payment #{$payment->id}",
                        $payment->id
                    );
                    $lease->refresh();

                    $landlord = User::find($lease->landlord_id);
                    if ($landlord) {
                        Mail::to($landlord->email)->queue(new OverpaymentNotification(
                            $payment,
                            $lease,
                            $lease->tenant,
                            $overpayment,
                            $lease->wallet_balance
                        ));
                    }
                }
            }

            $this->receiptService->createReceipt($payment, $invoice);

            if ($invoice && $invoice->lease?->tenant) {
                $invoice->load(['lease.tenant', 'lease.unit.building']);
                Mail::to($invoice->lease->tenant->email)->queue(new PaymentReceived($payment, $invoice));
            }

            DB::commit();

            $message = 'Payment of KES '.number_format($validated['amount'], 2).' recorded successfully!';
            if ($overpayment > 0) {
                $message .= ' KES '.number_format($overpayment, 2).' credited to wallet.';
            }

            return redirect()->route('finances.payments')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manual payment recording failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to record payment. Please try again.']);
        }
    }

    /**
     * Display a listing of all payments.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Payment::where('landlord_id', $landlordId)
            ->with([
                'invoice:id,invoice_number,total_due',
                'lease.tenant:id,name,email,mobile_number',
                'lease.unit.building:id,name',
            ]);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('invoice', function ($q) use ($search) {
                        $q->where('invoice_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('lease.tenant', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Payment method filter
        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        // Building filter
        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', function ($q) use ($request) {
                $q->where('building_id', $request->building_id);
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'payment_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $payments = $query->paginate(20)->withQueryString();

        // Calculate stats
        $stats = [
            'total_received' => Payment::where('landlord_id', $landlordId)->sum('amount'),
            'this_month' => Payment::where('landlord_id', $landlordId)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'payment_count' => Payment::where('landlord_id', $landlordId)->count(),
        ];

        // Get payment methods for filter
        $paymentMethods = [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money'],
            ['value' => 'paystack', 'label' => 'Paystack'],
            ['value' => 'stripe', 'label' => 'Stripe'],
        ];

        // Get buildings for filter dropdown
        $buildings = \App\Models\Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'stats' => $stats,
            'paymentMethods' => $paymentMethods,
            'buildings' => $buildings,
            'filters' => $request->only(['search', 'method', 'date_from', 'date_to', 'building_id', 'sort', 'direction']),
        ]);
    }

    /**
     * Initialize Paystack payment with split payment support
     */
    public function initializePaystack(Request $request, Invoice $invoice)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:'.($invoice->total_due - $invoice->amount_paid),
        ]);

        $tenant = $invoice->lease->tenant;
        $landlord = User::find($invoice->landlord_id);
        $reference = PaystackService::generateReference('INV');

        // Check if landlord needs a payout account
        if ($this->billingService->requiresPayoutAccount($landlord)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Online payments are not available. The landlord needs to connect a payout account first.',
                'requires_payout_account' => true,
            ], 400);
        }

        // Build base transaction data
        $transactionData = [
            'email' => $tenant->email,
            'amount' => $request->amount,
            'reference' => $reference,
            'callback_url' => route('payments.callback'),
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'tenant_name' => $tenant->name,
                'landlord_id' => $landlord->id,
                'amount' => $request->amount,
            ],
        ];

        // Check if split payments are available
        $splitConfig = $this->billingService->getSplitPaymentConfig($landlord, $request->amount);

        if ($splitConfig) {
            // Calculate fee preview for frontend
            $feeResult = $this->billingService->calculatePlatformFee($request->amount, $landlord);

            // Add split payment configuration
            $transactionData['subaccount_code'] = $splitConfig['subaccount_code'];
            $transactionData['bearer'] = $splitConfig['bearer'];

            // Add metadata for callback processing
            $transactionData['metadata']['is_split_payment'] = true;
            $transactionData['metadata']['payout_account_id'] = $splitConfig['payout_account']->id;
            $transactionData['metadata']['fee_calculation'] = $feeResult->toArray();

            $response = $this->paystackService->initializeSplitTransaction($transactionData);

            if ($response && $response['status']) {
                return response()->json([
                    'status' => 'success',
                    'data' => $response['data'],
                    'fee_info' => $feeResult->toArray(),
                ]);
            }
        } else {
            // Regular payment (no split) - fee recorded manually later
            $response = $this->paystackService->initializeTransaction($transactionData);

            if ($response && $response['status']) {
                return response()->json([
                    'status' => 'success',
                    'data' => $response['data'],
                    'fee_info' => null,
                ]);
            }
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to initialize payment',
        ], 500);
    }

    /**
     * Handle Paystack callback with platform fee recording
     */
    public function handleCallback(Request $request)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('invoices.index')->with('error', 'Payment reference not found');
        }

        $verification = $this->paystackService->verifyTransaction($reference);

        if (! $verification || ! $verification['status']) {
            return redirect()->route('invoices.index')->with('error', 'Payment verification failed');
        }

        $data = $verification['data'];

        if ($data['status'] !== 'success') {
            return redirect()->route('invoices.index')->with('error', 'Payment was not successful');
        }

        // Extract metadata
        $metadata = $data['metadata'] ?? [];

        // Check if this is an initial payment verification
        if (($metadata['type'] ?? null) === 'initial_payment' && isset($metadata['verification_id'])) {
            return $this->handleInitialPaymentCallback($data, $metadata);
        }

        // Regular invoice payment flow
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (! $invoiceId) {
            return redirect()->route('invoices.index')->with('error', 'Invoice not found in payment data');
        }

        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            return redirect()->route('invoices.index')->with('error', 'Invoice not found');
        }

        try {
            DB::beginTransaction();

            // Use pessimistic locking to prevent duplicate payments from race conditions
            $existingPayment = Payment::where('paystack_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                DB::rollBack();

                return redirect()->route('invoices.show', $invoice)->with('info', 'Payment already recorded');
            }

            // Lock the invoice for update to prevent concurrent modifications
            $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->first();

            // Convert amount from kobo to KES
            $amount = $data['amount'] / 100;

            // Extract split payment info from metadata
            $isSplitPayment = $metadata['is_split_payment'] ?? false;
            $payoutAccountId = $metadata['payout_account_id'] ?? null;

            // Create payment record
            $payment = $invoice->payments()->create([
                'landlord_id' => $invoice->landlord_id,
                'lease_id' => $invoice->lease_id,
                'payout_account_id' => $payoutAccountId,
                'amount' => $amount,
                'payment_method' => 'paystack',
                'payment_date' => now(),
                'reference' => $data['reference'],
                'paystack_reference' => $reference,
                'paystack_split_code' => $data['subaccount'] ?? null,
                'is_split_payment' => $isSplitPayment,
                'notes' => 'Paystack payment - '.($data['channel'] ?? 'online'),
            ]);

            // Record platform fee
            $landlord = User::find($invoice->landlord_id);
            $feeResult = $this->billingService->calculatePlatformFee($amount, $landlord);

            $payoutAccount = $payoutAccountId
                ? LandlordPayoutAccount::find($payoutAccountId)
                : null;

            $this->billingService->recordPlatformFee(
                payment: $payment,
                feeResult: $feeResult,
                payoutAccount: $payoutAccount,
                splitReference: $isSplitPayment ? $reference : null,
                splitDetails: $isSplitPayment ? [
                    'subaccount' => $data['subaccount'] ?? null,
                    'fees_split' => $data['fees_split'] ?? null,
                    'authorization' => $data['authorization'] ?? null,
                ] : null
            );

            // Auto-generate receipt
            $this->receiptService->createReceipt($payment, $invoice);

            $remainingBalance = $invoice->total_due - $invoice->amount_paid;
            $appliedAmount = min($amount, $remainingBalance);
            $overpayment = max(0, $amount - $remainingBalance);

            $newAmountPaid = $invoice->amount_paid + $appliedAmount;
            $newStatus = $newAmountPaid >= $invoice->total_due ? 'paid' : 'partial';

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status' => $newStatus,
            ]);

            if ($overpayment > 0) {
                $invoice->lease->creditToWallet(
                    $overpayment,
                    "Overpayment from Paystack payment #{$payment->id}",
                    $payment->id
                );
                $invoice->lease->refresh();

                $landlord = User::find($invoice->lease->landlord_id);
                if ($landlord) {
                    Mail::to($landlord->email)->queue(new OverpaymentNotification(
                        $payment,
                        $invoice->lease,
                        $invoice->lease->tenant,
                        $overpayment,
                        $invoice->lease->wallet_balance
                    ));
                }
            }

            DB::commit();

            $invoice->load(['lease.tenant', 'lease.unit.building']);
            Mail::to($invoice->lease->tenant->email)->send(new PaymentReceived($payment, $invoice));

            $message = 'Payment of KES '.number_format($amount, 2).' successful!';
            if ($overpayment > 0) {
                $message .= ' KES '.number_format($overpayment, 2).' credited to wallet.';
            }

            return redirect()->route('invoices.show', $invoice)->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment recording failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('invoices.show', $invoice)->with('error', 'Failed to record payment');
        }
    }

    /**
     * Handle Paystack webhook (server-to-server)
     * This endpoint receives POST requests from Paystack with signature verification
     */
    public function handleWebhook(Request $request)
    {
        // Verify webhook signature
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (! $signature || ! $this->paystackService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook signature verification failed', [
                'ip' => $request->ip(),
                'signature_present' => ! empty($signature),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info('Paystack webhook received', ['event' => $event, 'reference' => $data['reference'] ?? null]);

        if ($event === 'charge.success') {
            return $this->processSuccessfulCharge($data);
        }

        return response()->json(['status' => 'ignored']);
    }

    /**
     * Process a successful charge from webhook
     */
    protected function processSuccessfulCharge(array $data): \Illuminate\Http\JsonResponse
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return response()->json(['error' => 'No reference provided'], 400);
        }

        $metadata = $data['metadata'] ?? [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (! $invoiceId) {
            return response()->json(['status' => 'no_invoice']);
        }

        try {
            DB::beginTransaction();

            $existingPayment = Payment::where('paystack_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                DB::rollBack();

                return response()->json(['status' => 'already_processed']);
            }

            $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->first();

            if (! $invoice) {
                DB::rollBack();

                return response()->json(['error' => 'Invoice not found'], 404);
            }

            $amount = $data['amount'] / 100;
            $isSplitPayment = $metadata['is_split_payment'] ?? false;
            $payoutAccountId = $metadata['payout_account_id'] ?? null;

            $payment = $invoice->payments()->create([
                'landlord_id' => $invoice->landlord_id,
                'lease_id' => $invoice->lease_id,
                'payout_account_id' => $payoutAccountId,
                'amount' => $amount,
                'payment_method' => 'paystack',
                'payment_date' => now(),
                'reference' => $data['reference'],
                'paystack_reference' => $reference,
                'paystack_split_code' => $data['subaccount']['subaccount_code'] ?? null,
                'is_split_payment' => $isSplitPayment,
                'notes' => 'Paystack webhook - '.($data['channel'] ?? 'online'),
            ]);

            $landlord = User::find($invoice->landlord_id);
            $feeResult = $this->billingService->calculatePlatformFee($amount, $landlord);

            $payoutAccount = $payoutAccountId
                ? LandlordPayoutAccount::find($payoutAccountId)
                : null;

            $this->billingService->recordPlatformFee(
                payment: $payment,
                feeResult: $feeResult,
                payoutAccount: $payoutAccount,
                splitReference: $isSplitPayment ? $reference : null,
                splitDetails: $isSplitPayment ? [
                    'subaccount' => $data['subaccount'] ?? null,
                    'fees_split' => $data['fees_split'] ?? null,
                ] : null
            );

            // Auto-generate receipt
            $this->receiptService->createReceipt($payment, $invoice);

            $remainingBalance = $invoice->total_due - $invoice->amount_paid;
            $appliedAmount = min($amount, $remainingBalance);
            $overpayment = max(0, $amount - $remainingBalance);

            $newAmountPaid = $invoice->amount_paid + $appliedAmount;
            $newStatus = $newAmountPaid >= $invoice->total_due ? 'paid' : 'partial';

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status' => $newStatus,
            ]);

            if ($overpayment > 0) {
                $invoice->lease->creditToWallet(
                    $overpayment,
                    "Overpayment from Paystack webhook #{$payment->id}",
                    $payment->id
                );
                $invoice->lease->refresh();

                $landlord = User::find($invoice->lease->landlord_id);
                if ($landlord) {
                    Mail::to($landlord->email)->queue(new OverpaymentNotification(
                        $payment,
                        $invoice->lease,
                        $invoice->lease->tenant,
                        $overpayment,
                        $invoice->lease->wallet_balance
                    ));
                }
            }

            DB::commit();

            $invoice->load(['lease.tenant', 'lease.unit.building']);
            Mail::to($invoice->lease->tenant->email)->send(new PaymentReceived($payment, $invoice));

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Paystack webhook processing failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Get Paystack public key for frontend
     */
    public function getPublicKey()
    {
        return response()->json([
            'public_key' => $this->paystackService->getPublicKey(),
        ]);
    }

    /**
     * Download payment receipt as PDF
     */
    public function downloadReceipt(Payment $payment)
    {
        $this->authorize('downloadReceipt', $payment);

        $payment->load(['invoice.lease.tenant', 'invoice.lease.unit.building']);

        $pdf = Pdf::loadView('receipts.payment-receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
        ]);

        $filename = 'receipt-'.$payment->reference.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Send payment receipt via email
     */
    public function sendReceipt(Payment $payment)
    {
        $this->authorize('downloadReceipt', $payment);

        $payment->load(['invoice.lease.tenant', 'invoice.lease.unit.building']);

        if ($payment->invoice && $payment->invoice->lease && $payment->invoice->lease->tenant) {
            Mail::to($payment->invoice->lease->tenant->email)
                ->send(new PaymentReceived($payment, $payment->invoice));

            return back()->with('success', 'Receipt sent successfully.');
        }

        return back()->withErrors(['error' => 'Unable to send receipt - tenant not found.']);
    }

    /**
     * Void a payment
     */
    public function void(Request $request, Payment $payment)
    {
        $this->authorize('downloadReceipt', $payment);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($payment->is_voided) {
            return back()->withErrors(['error' => 'Payment is already voided.']);
        }

        try {
            DB::beginTransaction();

            $payment->update([
                'is_voided' => true,
                'voided_at' => now(),
                'void_reason' => $request->reason,
            ]);

            if ($payment->invoice_id) {
                $invoice = Invoice::lockForUpdate()->find($payment->invoice_id);
                if ($invoice) {
                    $newAmountPaid = max(0, $invoice->amount_paid - $payment->amount);
                    $newStatus = $newAmountPaid <= 0 ? 'sent' : ($newAmountPaid >= $invoice->total_due ? 'paid' : 'partial');

                    if ($invoice->status === 'voided') {
                        $newStatus = 'voided';
                    }

                    $invoice->update([
                        'amount_paid' => $newAmountPaid,
                        'status' => $newStatus,
                    ]);
                }
            }

            DB::commit();

            return back()->with('success', 'Payment voided successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment void failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to void payment.']);
        }
    }

    /**
     * Handle Paystack callback for initial payment verification
     */
    protected function handleInitialPaymentCallback(array $data, array $metadata)
    {
        $verificationId = $metadata['verification_id'];
        $verification = TenantPaymentVerification::find($verificationId);

        if (! $verification) {
            return redirect()->route('tenant.payment-required')
                ->with('error', 'Payment verification record not found');
        }

        if ($verification->isVerified()) {
            return redirect()->route('dashboard')
                ->with('info', 'Payment already verified');
        }

        try {
            DB::beginTransaction();

            // Check for duplicate payment
            $reference = $data['reference'];
            $existingPayment = Payment::where('paystack_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                DB::rollBack();

                return redirect()->route('tenant.payment-required')
                    ->with('info', 'Payment already recorded');
            }

            // Convert amount from kobo to KES
            $amount = $data['amount'] / 100;

            // Record the payment
            $payment = Payment::create([
                'landlord_id' => $verification->landlord_id,
                'lease_id' => $verification->lease_id,
                'amount' => $amount,
                'payment_method' => 'paystack',
                'payment_date' => now(),
                'reference' => $reference,
                'paystack_reference' => $reference,
                'notes' => 'Initial payment verification - '.($data['channel'] ?? 'online'),
            ]);

            // Update verification record
            $verification->recordPayment($amount);
            $verification->refresh();

            // Auto-verify if fully paid
            if ($verification->isFullyPaid()) {
                $verification->approve(0); // System auto-approval (no user ID)

                // Send approval email to tenant
                $tenant = $verification->lease->tenant;
                if ($tenant) {
                    Mail::to($tenant)->queue(new PaymentVerificationApproved($verification));
                }
            }

            DB::commit();

            $message = 'Payment of KES '.number_format($amount, 2).' successful!';
            if ($verification->isVerified()) {
                $message .= ' Your account has been verified. Welcome!';

                return redirect()->route('dashboard')->with('success', $message);
            }

            return redirect()->route('tenant.payment-required')
                ->with('success', $message.' Please wait for verification.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Initial payment recording failed', [
                'reference' => $data['reference'],
                'verification_id' => $verificationId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('tenant.payment-required')
                ->with('error', 'Failed to record payment. Please contact support.');
        }
    }

    /**
     * Show the bulk import form.
     */
    public function bulkImportForm()
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $buildings = Building::where('landlord_id', $landlordId)
            ->with('property:id,name')
            ->select('id', 'name', 'property_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'property_name' => $b->property?->name ?? 'Unknown Property',
                'display_name' => ($b->property?->name ?? 'Unknown').' - '.$b->name,
            ]);

        return Inertia::render('Finances/Payments/BulkImport', [
            'buildings' => $buildings,
        ]);
    }

    /**
     * Download bulk import CSV template.
     */
    public function downloadBulkTemplate(Request $request)
    {
        $user = auth()->user();
        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $mode = $request->query('mode', 'current');

        if ($mode === 'historical') {
            $headers = ['Unit Number', 'Tenant Name', 'Tenant Email', 'Payment Date', 'Amount', 'Payment Method', 'Reference'];
            $sample = ['A101', 'Former Tenant A', '', '2023-06-15', '12000', 'cash', ''];
            $sample2 = ['A102', 'Former Tenant B', '', '2023-08-01', '15000', 'mpesa', 'ABC123'];
            $filename = 'payments_historical_import_template.csv';
        } else {
            $headers = ['Unit Number', 'Tenant Name', 'Tenant Email', 'Invoice Number', 'Payment Date', 'Amount', 'Payment Method', 'Reference'];
            $sample = ['A101', 'John Doe', 'john@example.com', 'INV-202401-0001', '2024-01-15', '15750', 'mpesa', 'RBK12345678'];
            $sample2 = ['A102', 'Jane Smith', 'jane@example.com', '', '2024-01-15', '30000', 'bank_transfer', 'TRF-9876'];
            $filename = 'payments_bulk_import_template.csv';
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, $headers);
        fputcsv($csv, $sample);
        fputcsv($csv, $sample2);

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Validate CSV and return preview.
     */
    public function validateBulkImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'building_id' => 'required|integer|exists:buildings,id',
            'mode' => 'required|in:current,historical',
        ]);

        $user = auth()->user();
        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;
        $buildingId = $request->input('building_id');
        $mode = $request->input('mode');

        $building = Building::where('id', $buildingId)
            ->where('landlord_id', $landlordId)
            ->first();

        if (! $building) {
            return response()->json(['error' => 'Building not found or access denied.'], 403);
        }

        $file = $request->file('file');
        $rows = $this->parseCsv($file->getPathname());

        if (empty($rows)) {
            return response()->json(['error' => 'CSV file is empty or invalid format.'], 422);
        }

        $validRows = [];
        $invalidRows = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];

            $unitNumber = trim($row['unit_number'] ?? $row['Unit Number'] ?? '');
            $tenantName = trim($row['tenant_name'] ?? $row['Tenant Name'] ?? '');
            $tenantEmail = trim($row['tenant_email'] ?? $row['Tenant Email'] ?? '');
            $invoiceNumber = trim($row['invoice_number'] ?? $row['Invoice Number'] ?? '');
            $paymentDate = trim($row['payment_date'] ?? $row['Payment Date'] ?? '');
            $amount = trim($row['amount'] ?? $row['Amount'] ?? '');
            $paymentMethod = strtolower(trim($row['payment_method'] ?? $row['Payment Method'] ?? ''));
            $reference = trim($row['reference'] ?? $row['Reference'] ?? '');

            if (empty($unitNumber)) {
                $errors[] = 'Unit Number is required';
            }

            if ($mode === 'historical' && empty($tenantName)) {
                $errors[] = 'Tenant Name is required for historical imports';
            }

            if ($mode === 'current' && empty($tenantEmail)) {
                $errors[] = 'Tenant Email is required for current imports';
            } elseif ($mode === 'current' && ! empty($tenantEmail) && ! filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }

            if (empty($paymentDate)) {
                $errors[] = 'Payment Date is required';
            } elseif (! strtotime($paymentDate)) {
                $errors[] = 'Invalid date format (use YYYY-MM-DD)';
            } elseif ($mode === 'current' && strtotime($paymentDate) > time()) {
                $errors[] = 'Payment date cannot be in the future';
            }

            if (empty($amount)) {
                $errors[] = 'Amount is required';
            } elseif (! is_numeric($amount) || (float) $amount <= 0) {
                $errors[] = 'Amount must be a positive number';
            }

            $validMethods = ['cash', 'mpesa', 'bank_transfer', 'cheque', 'mobile_money'];
            if (empty($paymentMethod)) {
                $errors[] = 'Payment Method is required';
            } elseif (! in_array($paymentMethod, $validMethods)) {
                $errors[] = 'Payment Method must be one of: '.implode(', ', $validMethods);
            }

            $unit = Unit::where('building_id', $buildingId)
                ->where('unit_number', $unitNumber)
                ->first();

            if (! $unit && ! empty($unitNumber)) {
                $errors[] = "Unit '{$unitNumber}' not found in selected building";
            }

            if (! empty($errors)) {
                $invalidRows[] = [
                    'row' => $rowNumber,
                    'unit_number' => $unitNumber,
                    'tenant_name' => $tenantName,
                    'tenant_email' => $tenantEmail,
                    'amount' => $amount,
                    'errors' => $errors,
                ];

                continue;
            }

            if ($mode === 'historical') {
                $validRows[] = $this->validateHistoricalRow(
                    $rowNumber,
                    $unit,
                    $unitNumber,
                    $tenantName,
                    $tenantEmail,
                    $paymentDate,
                    (float) $amount,
                    $paymentMethod,
                    $reference,
                    $landlordId
                );
            } else {
                $result = $this->validateCurrentRow(
                    $rowNumber,
                    $unit,
                    $unitNumber,
                    $tenantName,
                    $tenantEmail,
                    $invoiceNumber,
                    $paymentDate,
                    (float) $amount,
                    $paymentMethod,
                    $reference,
                    $landlordId
                );

                if (isset($result['errors'])) {
                    $invalidRows[] = $result;
                } else {
                    $validRows[] = $result;
                }
            }
        }

        return response()->json([
            'total_rows' => count($rows),
            'valid_rows' => count($validRows),
            'invalid_rows' => count($invalidRows),
            'valid' => $validRows,
            'invalid' => $invalidRows,
            'mode' => $mode,
            'building_id' => $buildingId,
        ]);
    }

    /**
     * Validate a row for historical import mode.
     */
    private function validateHistoricalRow(
        int $rowNumber,
        ?Unit $unit,
        string $unitNumber,
        string $tenantName,
        string $tenantEmail,
        string $paymentDate,
        float $amount,
        string $paymentMethod,
        string $reference,
        int $landlordId
    ): array {
        return [
            'row' => $rowNumber,
            'unit_id' => $unit?->id,
            'unit_number' => $unitNumber,
            'tenant_name' => $tenantName,
            'tenant_email' => $tenantEmail,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'is_historical' => true,
            'will_create_tenant' => true,
            'allocations' => [],
            'wallet_credit' => 0,
        ];
    }

    /**
     * Validate a row for current tenant import mode.
     */
    private function validateCurrentRow(
        int $rowNumber,
        ?Unit $unit,
        string $unitNumber,
        string $tenantName,
        string $tenantEmail,
        string $invoiceNumber,
        string $paymentDate,
        float $amount,
        string $paymentMethod,
        string $reference,
        int $landlordId
    ): array {
        $tenant = User::where('landlord_id', $landlordId)
            ->where('role', 'tenant')
            ->where('email', $tenantEmail)
            ->where('is_archived', false)
            ->first();

        if (! $tenant) {
            return [
                'row' => $rowNumber,
                'unit_number' => $unitNumber,
                'tenant_name' => $tenantName,
                'tenant_email' => $tenantEmail,
                'amount' => $amount,
                'errors' => ["Active tenant not found with email: {$tenantEmail}"],
            ];
        }

        $activeLease = $tenant->lease;
        if (! $activeLease || $activeLease->unit_id !== $unit->id) {
            return [
                'row' => $rowNumber,
                'unit_number' => $unitNumber,
                'tenant_name' => $tenantName,
                'tenant_email' => $tenantEmail,
                'amount' => $amount,
                'errors' => ["Tenant does not have an active lease for unit {$unitNumber}"],
            ];
        }

        $allocations = [];
        $walletCredit = 0;
        $remainingAmount = $amount;

        if (! empty($invoiceNumber)) {
            $invoice = Invoice::where('landlord_id', $landlordId)
                ->where('invoice_number', $invoiceNumber)
                ->first();

            if (! $invoice) {
                return [
                    'row' => $rowNumber,
                    'unit_number' => $unitNumber,
                    'tenant_name' => $tenantName,
                    'tenant_email' => $tenantEmail,
                    'amount' => $amount,
                    'errors' => ["Invoice not found: {$invoiceNumber}"],
                ];
            }

            if ($invoice->lease && $invoice->lease->tenant_id !== $tenant->id) {
                return [
                    'row' => $rowNumber,
                    'unit_number' => $unitNumber,
                    'tenant_name' => $tenantName,
                    'tenant_email' => $tenantEmail,
                    'amount' => $amount,
                    'errors' => ["Invoice {$invoiceNumber} does not belong to this tenant"],
                ];
            }

            $outstanding = $invoice->getOutstandingBalance();
            $allocationAmount = min($remainingAmount, $outstanding);
            $allocations[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $allocationAmount,
                'outstanding_before' => $outstanding,
            ];
            $remainingAmount -= $allocationAmount;

            if ($remainingAmount > 0) {
                $walletCredit = $remainingAmount;
            }
        } else {
            $invoices = Invoice::where('landlord_id', $landlordId)
                ->whereHas('lease', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->orderBy('due_date', 'asc')
                ->get();

            foreach ($invoices as $invoice) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $outstanding = $invoice->getOutstandingBalance();
                if ($outstanding <= 0) {
                    continue;
                }

                $allocationAmount = min($remainingAmount, $outstanding);
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $allocationAmount,
                    'outstanding_before' => $outstanding,
                ];
                $remainingAmount -= $allocationAmount;
            }

            if ($remainingAmount > 0) {
                $walletCredit = $remainingAmount;
            }
        }

        return [
            'row' => $rowNumber,
            'unit_id' => $unit->id,
            'unit_number' => $unitNumber,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_email' => $tenantEmail,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'is_historical' => false,
            'allocations' => $allocations,
            'wallet_credit' => $walletCredit,
        ];
    }

    /**
     * Process validated bulk import.
     */
    public function processBulkImport(Request $request)
    {
        $mode = $request->input('mode', 'current');

        if ($mode === 'historical') {
            return $this->processHistoricalImport($request);
        }

        return $this->processCurrentImport($request);
    }

    /**
     * Process current tenant bulk import.
     */
    private function processCurrentImport(Request $request)
    {
        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.tenant_id' => 'required|integer',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_date' => 'required|date',
            'payments.*.payment_method' => 'required|string',
            'payments.*.allocations' => 'present|array',
        ]);

        $user = auth()->user();
        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $successCount = 0;
        $failedCount = 0;
        $totalAmount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->payments as $paymentData) {
                foreach ($paymentData['allocations'] as $allocation) {
                    $invoice = Invoice::where('id', $allocation['invoice_id'])
                        ->where('landlord_id', $landlordId)
                        ->lockForUpdate()
                        ->first();

                    if (! $invoice) {
                        throw new \Exception("Invoice {$allocation['invoice_id']} not found or not owned by landlord");
                    }

                    $payment = Payment::create([
                        'invoice_id' => $invoice->id,
                        'lease_id' => $invoice->lease_id,
                        'landlord_id' => $landlordId,
                        'amount' => $allocation['amount'],
                        'payment_method' => $paymentData['payment_method'],
                        'payment_date' => $paymentData['payment_date'],
                        'reference' => $paymentData['reference'] ?? null,
                        'notes' => 'Bulk import',
                    ]);

                    $invoice->update([
                        'amount_paid' => DB::raw("amount_paid + {$allocation['amount']}"),
                        'status' => DB::raw("CASE WHEN amount_paid + {$allocation['amount']} >= total_due THEN 'paid' WHEN amount_paid + {$allocation['amount']} > 0 THEN 'partial' ELSE status END"),
                    ]);

                    $this->receiptService->createReceipt($payment, $invoice);
                }

                if (($paymentData['wallet_credit'] ?? 0) > 0) {
                    if (! empty($paymentData['allocations'])) {
                        $firstInvoice = Invoice::find($paymentData['allocations'][0]['invoice_id']);
                        $lease = $firstInvoice ? $firstInvoice->lease : null;
                    } else {
                        $lease = Lease::where('tenant_id', $paymentData['tenant_id'])
                            ->where('landlord_id', $landlordId)
                            ->first();
                    }
                    if ($lease) {
                        $lease->creditToWallet($paymentData['wallet_credit'], 'Bulk import wallet credit');
                    }
                }

                $successCount++;
                $totalAmount += $paymentData['amount'];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'total_amount' => $totalAmount,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'success_count' => 0,
                'failed_count' => count($request->payments),
                'total_amount' => 0,
                'errors' => [['error' => $e->getMessage()]],
                'error' => 'Bulk import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process historical data bulk import.
     */
    private function processHistoricalImport(Request $request)
    {
        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.unit_id' => 'required|integer',
            'payments.*.tenant_name' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_date' => 'required|date',
            'payments.*.payment_method' => 'required|string',
            'building_id' => 'required|integer',
        ]);

        $user = auth()->user();
        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;
        $buildingId = $request->input('building_id');

        $successCount = 0;
        $failedCount = 0;
        $totalAmount = 0;
        $archivedTenantsCreated = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->payments as $paymentData) {
                $unitId = $paymentData['unit_id'];
                $tenantName = $paymentData['tenant_name'];
                $tenantEmail = $paymentData['tenant_email'] ?? null;
                $paymentDate = $paymentData['payment_date'];

                $archivedTenant = $this->findOrCreateArchivedTenant(
                    $landlordId,
                    $unitId,
                    $tenantName,
                    $tenantEmail,
                    $archivedTenantsCreated
                );

                $historicalLease = $this->findOrCreateHistoricalLease(
                    $landlordId,
                    $unitId,
                    $archivedTenant->id,
                    $paymentDate
                );

                Payment::create([
                    'invoice_id' => null,
                    'lease_id' => $historicalLease->id,
                    'landlord_id' => $landlordId,
                    'amount' => $paymentData['amount'],
                    'payment_method' => $paymentData['payment_method'],
                    'payment_date' => $paymentDate,
                    'reference' => $paymentData['reference'] ?? null,
                    'notes' => 'Historical import',
                ]);

                $successCount++;
                $totalAmount += $paymentData['amount'];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'total_amount' => $totalAmount,
                'archived_tenants_created' => $archivedTenantsCreated,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'success_count' => 0,
                'failed_count' => count($request->payments),
                'total_amount' => 0,
                'archived_tenants_created' => 0,
                'errors' => [['error' => $e->getMessage()]],
                'error' => 'Historical import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find or create an archived tenant record for historical imports.
     */
    private function findOrCreateArchivedTenant(
        int $landlordId,
        int $unitId,
        string $tenantName,
        ?string $tenantEmail,
        int &$createdCount
    ): User {
        $existingTenant = User::where('landlord_id', $landlordId)
            ->where('role', 'tenant')
            ->where('is_archived', true)
            ->where('name', $tenantName)
            ->first();

        if ($existingTenant) {
            return $existingTenant;
        }

        $email = $tenantEmail ?: 'archived_'.Str::slug($tenantName).'_'.$unitId.'_'.time().'@placeholder.local';

        $tenant = User::create([
            'name' => $tenantName,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'role' => 'tenant',
            'landlord_id' => $landlordId,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $createdCount++;

        return $tenant;
    }

    /**
     * Find or create a historical lease record.
     */
    private function findOrCreateHistoricalLease(
        int $landlordId,
        int $unitId,
        int $tenantId,
        string $paymentDate
    ): Lease {
        $existingLease = Lease::where('landlord_id', $landlordId)
            ->where('unit_id', $unitId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', false)
            ->first();

        if ($existingLease) {
            if (strtotime($existingLease->end_date) < strtotime($paymentDate)) {
                $existingLease->update(['end_date' => $paymentDate]);
            }
            if (strtotime($existingLease->start_date) > strtotime($paymentDate)) {
                $existingLease->update(['start_date' => $paymentDate]);
            }

            return $existingLease;
        }

        return Lease::create([
            'unit_id' => $unitId,
            'tenant_id' => $tenantId,
            'landlord_id' => $landlordId,
            'start_date' => $paymentDate,
            'end_date' => $paymentDate,
            'rent_amount' => 0,
            'deposit_amount' => 0,
            'is_active' => false,
        ]);
    }

    /**
     * Parse CSV file into array of rows.
     */
    private function parseCsv(string $filepath): array
    {
        $rows = [];
        $handle = fopen($filepath, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $headers = array_map('trim', $headers);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < count($headers)) {
                $data = array_pad($data, count($headers), '');
            } elseif (count($data) > count($headers)) {
                $data = array_slice($data, 0, count($headers));
            }
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }
}
