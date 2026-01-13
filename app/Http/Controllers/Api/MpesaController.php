<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InitiateMpesaPaymentRequest;
use App\Models\Invoice;
use App\Services\MpesaService;
use Illuminate\Http\Request;

class MpesaController extends Controller
{
    public function __construct(protected MpesaService $mpesaService) {}

    public function initiateStkPush(InitiateMpesaPaymentRequest $request)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);

        if (! $this->mpesaService->isConfigured()) {
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
        ]);

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

    public function checkStatus(Request $request)
    {
        $request->validate([
            'checkout_request_id' => 'required|string',
        ]);

        $result = $this->mpesaService->querySTKStatus($request->checkout_request_id);

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
