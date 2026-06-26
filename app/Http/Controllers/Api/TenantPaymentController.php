<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Integration\PaymentGatewayUnreachableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckMpesaStatusRequest;
use App\Http\Requests\Api\InitiateIntaSendPaymentRequest;
use App\Http\Requests\Api\InitiateMpesaPaymentRequest;
use App\Http\Requests\Api\InitiatePaystackPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\IntaSendTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Services\IntaSendService;
use App\Services\MpesaService;
use App\Services\Payment\ReceiptGenerator;
use App\Services\PaymentGatewayManager;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantPaymentController extends Controller
{
    public function __construct(
        protected MpesaService $mpesaService,
        protected PaystackService $paystackService,
        protected PaymentGatewayManager $gatewayManager,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 15), 50);

        $payments = Payment::whereHas('lease', function ($query) use ($user) {
            $query->where('tenant_id', $user->id);
        })
            ->with(['invoice', 'lease.unit.building'])
            ->orderBy('payment_date', 'desc')
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    public function show(Request $request, Payment $payment)
    {
        $user = $request->user();

        if ($payment->lease->tenant_id !== $user->id) {
            abort(403, 'You do not have access to this payment.');
        }

        $payment->load(['invoice', 'lease.unit.building']);

        return new PaymentResource($payment);
    }

    public function receipt(Request $request, Payment $payment, ReceiptGenerator $generator)
    {
        if ($payment->lease->tenant_id !== $request->user()->id) {
            abort(403, 'You do not have access to this receipt.');
        }

        return $generator->download($payment);
    }

    public function initiateMpesa(InitiateMpesaPaymentRequest $request)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);
        $paymentConfig = PaymentConfiguration::where('landlord_id', $invoice->landlord_id)->first();

        $hasLandlordConfig = $paymentConfig && $paymentConfig->hasMpesaSTKConfig();
        if (! $hasLandlordConfig && ! $this->mpesaService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'M-Pesa payments are not configured.',
            ], 503);
        }

        try {
            $result = $this->mpesaService->initiateSTKPush([
                'phone' => $request->phone,
                'amount' => $request->amount,
                'account_reference' => $invoice->invoice_number,
                'description' => "Payment for Invoice {$invoice->invoice_number}",
                'callback_url' => route('webhooks.mpesa.stk-callback'),
            ], $paymentConfig);
        } catch (PaymentGatewayUnreachableException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], $e->getStatusCode());
        }

        $failureResponse = $this->mpesaStkResultResponse($result);
        if ($failureResponse !== null) {
            return $failureResponse;
        }

        return response()->json([
            'success' => true,
            'message' => 'STK Push sent. Please enter your M-Pesa PIN on your phone.',
            'checkout_request_id' => $result['CheckoutRequestID'],
            'merchant_request_id' => $result['MerchantRequestID'],
        ]);
    }

    private function mpesaStkResultResponse(?array $result): ?\Illuminate\Http\JsonResponse
    {
        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate M-Pesa payment.',
            ], 500);
        }

        if (($result['ResponseCode'] ?? '') !== '0') {
            return response()->json([
                'success' => false,
                'message' => $result['ResponseDescription'] ?? 'M-Pesa request failed.',
            ], 400);
        }

        return null;
    }

    public function checkMpesaStatus(CheckMpesaStatusRequest $request)
    {
        $validated = $request->validated();
        $checkoutRequestId = $validated['checkout_request_id'];

        $payment = Payment::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

        if ($payment) {
            return response()->json([
                'success' => true,
                'status' => 'completed',
                'payment_id' => $payment->id,
                'message' => 'Payment received successfully.',
            ]);
        }

        $tenant = $request->user();
        [$lease, $paymentConfig, $configError] = $this->resolveLeaseAndMpesaConfig($tenant);

        if ($configError !== null) {
            return $configError;
        }

        $result = $this->mpesaService->querySTKStatus($checkoutRequestId, $paymentConfig);

        if (! $result) {
            return response()->json([
                'success' => false,
                'status' => 'pending',
                'message' => 'Waiting for payment confirmation...',
            ]);
        }

        return $this->mpesaStatusResultResponse($result);
    }

    private function resolveLeaseAndMpesaConfig($tenant): array
    {
        $lease = $tenant->leases()->where('is_active', true)->first();

        if (! $lease) {
            return [null, null, response()->json([
                'success' => false,
                'message' => 'No active lease found.',
            ], 404)];
        }

        $paymentConfig = PaymentConfiguration::where('landlord_id', $lease->landlord_id)->first();

        if (! $paymentConfig || ! $paymentConfig->hasMpesaApiConfig()) {
            return [null, null, response()->json([
                'success' => false,
                'message' => 'M-Pesa payments are not configured.',
            ], 503)];
        }

        return [$lease, $paymentConfig, null];
    }

    private function mpesaStatusResultResponse(array $result): \Illuminate\Http\JsonResponse
    {
        $resultCode = $result['ResultCode'] ?? -1;

        if ($resultCode === '0' || $resultCode === 0) {
            return response()->json([
                'success' => true,
                'status' => 'processing',
                'message' => 'Payment is being processed...',
            ]);
        }

        if ($resultCode === '1032') {
            return response()->json([
                'success' => false,
                'status' => 'cancelled',
                'message' => 'Payment was cancelled by user.',
            ]);
        }

        return response()->json([
            'success' => false,
            'status' => 'failed',
            'message' => $result['ResultDesc'] ?? 'Payment failed.',
        ]);
    }

    public function initiatePaystack(InitiatePaystackPaymentRequest $request)
    {
        $user = $request->user();
        $invoice = Invoice::findOrFail($request->invoice_id);
        $paymentConfig = PaymentConfiguration::where('landlord_id', $invoice->landlord_id)->first();

        $paystackService = new PaystackService($paymentConfig);

        if (! $paystackService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Online card payments are not configured.',
            ], 503);
        }

        $reference = PaystackService::generateReference('API');

        try {
            $result = $paystackService->initializeTransaction([
                'email' => $user->email,
                'amount' => $request->amount,
                'currency' => $invoice->currency?->value ?? 'KES',
                'reference' => $reference,
                'callback_url' => $request->callback_url ?? route('payments.callback'),
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tenant_name' => $user->name,
                    'landlord_id' => $invoice->landlord_id,
                    'amount' => $request->amount,
                    'source' => 'api',
                ],
            ]);
        } catch (PaymentGatewayUnreachableException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], $e->getStatusCode());
        }

        if (! $result || ! $result['status']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize Paystack payment.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'authorization_url' => $result['data']['authorization_url'],
            'reference' => $result['data']['reference'],
            'access_code' => $result['data']['access_code'],
        ]);
    }

    public function initiateIntaSend(InitiateIntaSendPaymentRequest $request)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);
        $paymentConfig = PaymentConfiguration::where('landlord_id', $invoice->landlord_id)->first();

        if (! $paymentConfig?->hasIntaSendConfig()) {
            return response()->json([
                'success' => false,
                'message' => 'IntaSend payments are not configured.',
            ], 503);
        }

        $intaSendService = new IntaSendService($paymentConfig);
        $reference = IntaSendService::generateReference('ITS');

        $transaction = IntaSendTransaction::create([
            'landlord_id' => $invoice->landlord_id,
            'invoice_id' => $invoice->id,
            'api_ref' => $reference,
            'phone_number' => $intaSendService->formatPhoneNumber($request->phone),
            'amount' => $request->amount,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        try {
            $result = $intaSendService->initializeMpesaStkPush(
                $request->amount,
                $request->phone,
                $reference,
                $this->buildIntaSendWalletOptions($paymentConfig)
            );
        } catch (PaymentGatewayUnreachableException $e) {
            $transaction->markFailed('Gateway unreachable: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], $e->getStatusCode());
        }

        if (! $result || ! isset($result['invoice']['invoice_id'])) {
            $transaction->markFailed('STK Push initiation failed');

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate IntaSend payment.',
            ], 500);
        }

        $intasendInvoiceId = $result['invoice']['invoice_id'];

        $this->persistIntaSendInvoiceId($transaction, $reference, $intasendInvoiceId, $invoice->id);

        return response()->json([
            'success' => true,
            'message' => 'STK Push sent. Please enter your M-Pesa PIN on your phone.',
            'intasend_invoice_id' => $intasendInvoiceId,
            'api_ref' => $reference,
        ]);
    }

    private function buildIntaSendWalletOptions(PaymentConfiguration $paymentConfig): ?array
    {
        if (! $paymentConfig->intasend_wallet_id) {
            return null;
        }

        return ['wallet_id' => $paymentConfig->intasend_wallet_id];
    }

    private function persistIntaSendInvoiceId(
        IntaSendTransaction $transaction,
        string $reference,
        string $intasendInvoiceId,
        int $invoiceId,
    ): void {
        try {
            $transaction->update(['intasend_invoice_id' => $intasendInvoiceId]);
        } catch (\Throwable $e) {
            Log::emergency('IntaSend transaction update failed after successful STK push', [
                'transaction_id' => $transaction->id,
                'api_ref' => $reference,
                'intasend_invoice_id' => $intasendInvoiceId,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
