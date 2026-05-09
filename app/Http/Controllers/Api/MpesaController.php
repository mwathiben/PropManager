<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Integration\PaymentGatewayUnreachableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InitiateMpesaPaymentRequest;
use App\Http\Requests\Api\MpesaCheckStatusRequest;
use App\Models\Invoice;
use App\Models\PaymentConfiguration;
use App\Services\MpesaService;

class MpesaController extends Controller
{
    public function __construct(protected MpesaService $mpesaService) {}

    public function initiateStkPush(InitiateMpesaPaymentRequest $request)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);
        $paymentConfig = PaymentConfiguration::where('landlord_id', $invoice->landlord_id)->first();

        if (! $paymentConfig || ! $paymentConfig->hasMpesaSTKConfig()) {
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
            // HANDLE-1: 503 + retry message instead of generic 500 — this is
            // a transient outage, not a permanent failure.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], $e->getStatusCode());
        }

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
                'error_code' => $result['ResponseCode'] ?? null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'STK Push sent. Please enter your M-Pesa PIN on your phone.',
            'checkout_request_id' => $result['CheckoutRequestID'],
            'merchant_request_id' => $result['MerchantRequestID'],
        ]);
    }

    public function checkStatus(MpesaCheckStatusRequest $request)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);
        $paymentConfig = PaymentConfiguration::where('landlord_id', $invoice->landlord_id)->first();

        if (! $paymentConfig || ! $paymentConfig->hasMpesaSTKConfig()) {
            return response()->json([
                'success' => false,
                'message' => 'M-Pesa payments are not configured.',
            ], 503);
        }

        $result = $this->mpesaService->querySTKStatus($request->checkout_request_id, $paymentConfig);

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status.',
            ], 500);
        }

        $resultCode = $result['ResultCode'] ?? -1;

        return response()->json([
            'success' => $resultCode === '0' || $resultCode === 0,
            'result_code' => $resultCode,
            'result_desc' => $result['ResultDesc'] ?? 'Unknown status',
        ]);
    }
}
