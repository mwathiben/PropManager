<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckMpesaStatusRequest;
use App\Http\Requests\Api\InitiateMpesaPaymentRequest;
use App\Http\Requests\Api\InitiatePaystackPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Services\MpesaService;
use App\Services\PaymentGatewayManager;
use App\Services\PaystackService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

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

    public function receipt(Request $request, Payment $payment)
    {
        $user = $request->user();

        if ($payment->lease->tenant_id !== $user->id) {
            abort(403, 'You do not have access to this receipt.');
        }

        $payment->load(['invoice.lease.tenant', 'invoice.lease.unit.building']);

        $settings = InvoiceSetting::where('landlord_id', $payment->landlord_id)->first()
            ?? new InvoiceSetting;

        $pdf = Pdf::loadView('receipts.payment-receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'settings' => $settings,
        ]);

        return $pdf->download("receipt-{$payment->reference}.pdf");
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

        $result = $this->mpesaService->initiateSTKPush([
            'phone' => $request->phone,
            'amount' => $request->amount,
            'account_reference' => $invoice->invoice_number,
            'description' => "Payment for Invoice {$invoice->invoice_number}",
            'callback_url' => route('webhooks.mpesa.stk-callback'),
        ], $paymentConfig);

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

        return response()->json([
            'success' => true,
            'message' => 'STK Push sent. Please enter your M-Pesa PIN on your phone.',
            'checkout_request_id' => $result['CheckoutRequestID'],
            'merchant_request_id' => $result['MerchantRequestID'],
        ]);
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

        $result = $this->mpesaService->querySTKStatus($checkoutRequestId);

        if (! $result) {
            return response()->json([
                'success' => false,
                'status' => 'pending',
                'message' => 'Waiting for payment confirmation...',
            ]);
        }

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
        if (! $this->paystackService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Online card payments are not configured.',
            ], 503);
        }

        $user = $request->user();
        $invoice = Invoice::findOrFail($request->invoice_id);

        $reference = PaystackService::generateReference('API');

        $result = $this->paystackService->initializeTransaction([
            'email' => $user->email,
            'amount' => $request->amount,
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
}
