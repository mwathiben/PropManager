<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\Payment\InitializePaystackRequest;
use App\Http\Requests\Payment\ProcessBulkImportRequest;
use App\Http\Requests\Payment\ValidateBulkImportRequest;
use App\Http\Requests\Payment\VoidPaymentRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Traits\WithLandlordScope;
use App\Mail\OverpaymentNotification;
use App\Mail\PaymentReceived;
use App\Mail\PaymentVerificationApproved;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\TenantPaymentVerification;
use App\Models\User;
use App\Services\BillingModelService;
use App\Services\BulkImport\BulkImportValidator;
use App\Services\Payment\BulkPaymentProcessor;
use App\Services\Payment\ManualPaymentHandler;
use App\Services\Payment\PaystackCallbackHandler;
use App\Services\Payment\PaystackHandlerResult;
use App\Services\PaystackService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PaymentController extends Controller
{
    use WithLandlordScope;

    protected BillingModelService $billingService;

    public function __construct(
        BillingModelService $billingService,
    ) {
        $this->billingService = $billingService;
    }

    /**
     * Show the record payment form.
     */
    public function create(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        // Get enabled payment methods from settings
        $settings = \App\Models\Setting::where('landlord_id', $landlordId)
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

        return Inertia::render('Finances/Payments/Record', [
            'paymentMethods' => $paymentMethods,
            'buildings' => $this->getBuildingsForDropdown(),
        ]);
    }

    /**
     * Store a manually recorded payment.
     */
    public function storeManual(StorePaymentRequest $request, ManualPaymentHandler $handler)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        try {
            $result = $handler->record($landlordId, $request->validated());

            if ($result->hasOverpayment()) {
                $this->sendPendingOverpaymentNotifications([$result->overpaymentNotification()]);
            }

            return redirect()->route('finances.payments')->with('success', $result->successMessage());
        } catch (\Exception $e) {
            Log::error('Manual payment recording failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to record payment. Please try again.']);
        }
    }

    /**
     * Display a listing of all payments.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Payment::where('landlord_id', $landlordId)
            ->with([
                'invoice:id,invoice_number,total_due,lease_id',
                'invoice.lease.tenant:id,name,email',
                'lease.tenant:id,name,email,mobile_number',
                'lease.unit:id,unit_number,building_id',
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

        // Calculate stats using DB-agnostic Eloquent queries
        $now = now();
        $totalReceived = Payment::where('landlord_id', $landlordId)->sum('amount') ?? 0;
        $thisMonth = Payment::where('landlord_id', $landlordId)
            ->whereMonth('payment_date', $now->month)
            ->whereYear('payment_date', $now->year)
            ->sum('amount') ?? 0;
        $paymentCount = Payment::where('landlord_id', $landlordId)->count();

        $stats = [
            'total_received' => (float) $totalReceived,
            'this_month' => (float) $thisMonth,
            'payment_count' => (int) $paymentCount,
        ];

        // Get payment methods for filter
        $paymentMethods = [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money'],
            ['value' => 'paystack', 'label' => 'Paystack'],
            ['value' => 'stripe', 'label' => 'Stripe'],
        ];

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'stats' => $stats,
            'paymentMethods' => $paymentMethods,
            'buildings' => $this->getBuildingsForDropdown(),
            'filters' => $request->only(['search', 'method', 'date_from', 'date_to', 'building_id', 'sort', 'direction']),
        ]);
    }

    /**
     * Initialize Paystack payment with split payment support
     */
    public function initializePaystack(InitializePaystackRequest $request, Invoice $invoice)
    {
        $validated = $request->validated();

        $lease = $invoice->lease;
        if (! $lease || ! $lease->tenant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice has no associated lease or tenant',
            ], 400);
        }

        $tenant = $lease->tenant;
        $landlord = User::find($invoice->landlord_id);

        if (! $landlord) {
            return response()->json([
                'status' => 'error',
                'message' => 'Landlord not found',
            ], 400);
        }

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

        // Get landlord's payment configuration for per-landlord credentials
        $paymentConfig = PaymentConfiguration::where('landlord_id', $landlord->id)->first();
        $paystackService = new PaystackService($paymentConfig);

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

            $response = $paystackService->initializeSplitTransaction($transactionData);

            if ($response && $response['status']) {
                return response()->json([
                    'status' => 'success',
                    'data' => $response['data'],
                    'fee_info' => $feeResult->toArray(),
                ]);
            }
        } else {
            // Regular payment (no split) - fee recorded manually later
            $response = $paystackService->initializeTransaction($transactionData);

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
     * Handle Paystack callback (browser redirect after payment)
     */
    public function handleCallback(Request $request, PaystackCallbackHandler $handler)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('invoices.index')->with('error', 'Payment reference not found');
        }

        $result = $handler->processCallback(
            $reference,
            $request->user()?->id,
            fn ($pending) => $this->sendPendingOverpaymentNotifications($pending)
        );

        if ($result->isInitialPayment()) {
            return $this->handleInitialPaymentCallback($result->data, $result->metadata);
        }

        return $this->mapCallbackResult($result);
    }

    /**
     * Handle Paystack webhook (server-to-server)
     */
    public function handleWebhook(Request $request, PaystackCallbackHandler $handler)
    {
        $result = $handler->processWebhook(
            $request->getContent(),
            $request->header('x-paystack-signature'),
            fn ($pending) => $this->sendPendingOverpaymentNotifications($pending)
        );

        return response()->json($result->toResponse(), $result->httpStatus());
    }

    private function mapCallbackResult(PaystackHandlerResult $result): \Illuminate\Http\RedirectResponse
    {
        if ($result->isSuccess() && $result->processResult?->invoice) {
            $invoice = $result->processResult->invoice;

            return redirect()->route('invoices.show', $invoice)
                ->with('success', $result->processResult->getSuccessMessage());
        }

        if ($result->isAlreadyProcessed()) {
            return redirect()->route('invoices.index')->with('info', 'Payment already recorded');
        }

        $message = $result->errorMessage ?? 'Payment processing failed';

        return redirect()->route('invoices.index')->with('error', $message);
    }

    /**
     * Get Paystack public key for frontend
     */
    public function getPublicKey(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() || $user->isTenant() ? $user->landlord_id : $user->id;

        $paymentConfig = PaymentConfiguration::where('landlord_id', $landlordId)->first();
        $paystackService = new PaystackService($paymentConfig);

        return response()->json([
            'public_key' => $paystackService->getPublicKey(),
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
    public function void(VoidPaymentRequest $request, Payment $payment)
    {
        $this->authorize('downloadReceipt', $payment);

        $validated = $request->validated();

        if ($payment->is_voided) {
            return back()->withErrors(['error' => 'Payment is already voided.']);
        }

        try {
            DB::beginTransaction();

            $payment->update([
                'is_voided' => true,
                'voided_at' => now(),
                'void_reason' => $validated['reason'],
            ]);

            if ($payment->invoice_id) {
                $invoice = Invoice::lockForUpdate()->find($payment->invoice_id);
                if ($invoice) {
                    $newAmountPaid = max(0, $invoice->amount_paid - $payment->amount);
                    $newStatus = $newAmountPaid <= 0 ? InvoiceStatus::Sent : ($newAmountPaid >= $invoice->total_due ? InvoiceStatus::Paid : InvoiceStatus::Partial);

                    if ($invoice->status === InvoiceStatus::Voided) {
                        $newStatus = InvoiceStatus::Voided;
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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        return Inertia::render('Finances/Payments/BulkImport', [
            'buildings' => $this->getBuildingsWithProperty(),
        ]);
    }

    /**
     * Download bulk import CSV template.
     */
    public function downloadBulkTemplate(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
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
    public function validateBulkImport(ValidateBulkImportRequest $request)
    {
        $validated = $request->validated();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $result = BulkImportValidator::make()
            ->forMode($validated['mode'])
            ->forLandlord($landlordId)
            ->forBuilding($validated['building_id'])
            ->withFile($request->file('file'))
            ->validate();

        if (! $result['success']) {
            $statusCode = str_contains($result['error'], 'access denied') ? 403 : 422;

            return response()->json(['error' => $result['error']], $statusCode);
        }

        unset($result['success']);

        return response()->json($result);
    }

    public function processBulkImport(ProcessBulkImportRequest $request, BulkPaymentProcessor $processor)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $result = $processor->process($landlordId, $request->validated());

        return response()->json($result->toArray(), $result->success ? 200 : 500);
    }

    /**
     * Send queued overpayment notifications after DB commit.
     */
    private function sendPendingOverpaymentNotifications(array $pendingOverpayments): void
    {
        if (empty($pendingOverpayments)) {
            return;
        }

        $leaseIds = collect($pendingOverpayments)->pluck('lease_id')->unique()->filter();
        $paymentIds = collect($pendingOverpayments)->pluck('payment_id')->unique()->filter();

        $leases = Lease::whereIn('id', $leaseIds)
            ->with(['tenant', 'landlord'])
            ->get()
            ->keyBy('id');

        $payments = Payment::whereIn('id', $paymentIds)
            ->get()
            ->keyBy('id');

        foreach ($pendingOverpayments as $p) {
            $lease = $leases->get($p['lease_id']);
            $payment = $payments->get($p['payment_id']);

            if (! $lease || ! $payment) {
                continue;
            }

            $tenant = $lease->tenant;
            $landlord = $lease->landlord;

            if ($landlord && $tenant) {
                Mail::to($landlord->email)->queue(new OverpaymentNotification(
                    $payment,
                    $lease,
                    $tenant,
                    $p['overpayment'],
                    $lease->wallet_balance
                ));
            }
        }
    }
}
