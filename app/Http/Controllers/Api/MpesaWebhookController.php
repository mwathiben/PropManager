<?php

namespace App\Http\Controllers\Api;

use App\Events\MpesaPaymentStatusChanged;
use App\Events\PaymentReceived as PaymentReceivedEvent;
use App\Http\Controllers\Controller;
use App\Mail\PaymentReceived;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillingModelService;
use App\Services\MpesaService;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MpesaWebhookController extends Controller
{
    public function __construct(
        protected MpesaService $mpesaService,
        protected BillingModelService $billingService,
        protected ReceiptService $receiptService
    ) {}

    public function stkCallback(Request $request)
    {
        if (! $this->mpesaService->validateWebhookIP($request->ip())) {
            Log::warning('M-Pesa STK callback from unauthorized IP', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected']);
        }

        $callback = $request->input('Body.stkCallback');

        if (! $callback) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
        }

        Log::info('M-Pesa STK callback received', [
            'checkout_request_id' => $callback['CheckoutRequestID'] ?? null,
            'result_code' => $callback['ResultCode'] ?? null,
        ]);

        if (($callback['ResultCode'] ?? -1) !== 0) {
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
            $resultDesc = $callback['ResultDesc'] ?? 'Unknown error';
            $isCancelled = str_contains(strtolower($resultDesc), 'cancel');

            Log::info('M-Pesa STK payment failed or cancelled', [
                'checkout_request_id' => $checkoutRequestId,
                'result_desc' => $resultDesc,
            ]);

            if ($checkoutRequestId) {
                MpesaPaymentStatusChanged::dispatch(
                    $checkoutRequestId,
                    $isCancelled ? 'cancelled' : 'failed',
                    null,
                    null,
                    null,
                    $resultDesc
                );
            }

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $items = collect($callback['CallbackMetadata']['Item'] ?? [])
            ->mapWithKeys(fn ($item) => [$item['Name'] => $item['Value'] ?? null]);

        $mpesaReceiptNumber = $items->get('MpesaReceiptNumber');
        $amount = $items->get('Amount');
        $phone = $items->get('PhoneNumber');
        $transactionDate = $items->get('TransactionDate');

        if (! $mpesaReceiptNumber || ! $amount) {
            Log::error('M-Pesa STK callback missing required data', ['items' => $items->toArray()]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $this->processPayment([
            'checkout_request_id' => $callback['CheckoutRequestID'],
            'mpesa_receipt_number' => $mpesaReceiptNumber,
            'amount' => $amount,
            'phone' => $phone,
            'transaction_date' => $transactionDate,
        ]);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function c2bValidation(Request $request)
    {
        if (! $this->mpesaService->validateWebhookIP($request->ip())) {
            Log::warning('M-Pesa C2B validation from unauthorized IP', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']);
        }

        $accountReference = $request->input('BillRefNumber');
        $amount = $request->input('TransAmount');

        Log::info('M-Pesa C2B validation request', [
            'account_reference' => $accountReference,
            'amount' => $amount,
            'phone' => substr($request->input('MSISDN', ''), -4),
        ]);

        $invoice = $this->findInvoiceByReference($accountReference);

        if (! $invoice) {
            Log::warning('M-Pesa C2B: Invoice not found', ['account_reference' => $accountReference]);

            return response()->json([
                'ResultCode' => 'C2B00011',
                'ResultDesc' => 'Invalid Account Number',
            ]);
        }

        $remainingDue = $invoice->total_due - $invoice->amount_paid;
        if ($amount > $remainingDue * 1.1) {
            Log::warning('M-Pesa C2B: Amount exceeds invoice balance', [
                'amount' => $amount,
                'remaining_due' => $remainingDue,
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function c2bConfirmation(Request $request)
    {
        if (! $this->mpesaService->validateWebhookIP($request->ip())) {
            Log::warning('M-Pesa C2B confirmation from unauthorized IP', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected']);
        }

        Log::info('M-Pesa C2B confirmation received', [
            'transaction_id' => $request->input('TransID'),
            'amount' => $request->input('TransAmount'),
            'account_reference' => $request->input('BillRefNumber'),
        ]);

        $this->processPayment([
            'mpesa_receipt_number' => $request->input('TransID'),
            'amount' => $request->input('TransAmount'),
            'phone' => $request->input('MSISDN'),
            'account_reference' => $request->input('BillRefNumber'),
            'transaction_date' => $request->input('TransTime'),
            'first_name' => $request->input('FirstName'),
            'last_name' => $request->input('LastName'),
        ]);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    protected function processPayment(array $data): void
    {
        $receiptNumber = $data['mpesa_receipt_number'];

        try {
            DB::beginTransaction();

            $existingPayment = Payment::where('mpesa_transaction_id', $receiptNumber)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                DB::rollBack();
                Log::info('M-Pesa payment already processed', ['receipt' => $receiptNumber]);

                return;
            }

            $invoice = null;
            if (! empty($data['account_reference'])) {
                $invoice = $this->findInvoiceByReference($data['account_reference']);
            }

            if (! empty($data['checkout_request_id']) && ! $invoice) {
                $invoice = $this->findInvoiceByCheckoutRequest($data['checkout_request_id']);
            }

            if (! $invoice) {
                DB::rollBack();
                Log::warning('M-Pesa payment: No matching invoice found', $data);

                return;
            }

            $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->first();
            $amount = (float) $data['amount'];

            $payment = $invoice->payments()->create([
                'landlord_id' => $invoice->landlord_id,
                'lease_id' => $invoice->lease_id,
                'amount' => $amount,
                'payment_method' => 'mobile_money',
                'payment_date' => now(),
                'reference' => 'MPESA-'.$receiptNumber,
                'mpesa_transaction_id' => $receiptNumber,
                'mpesa_checkout_request_id' => $data['checkout_request_id'] ?? null,
                'notes' => 'M-Pesa payment from '.($data['phone'] ?? 'unknown'),
            ]);

            $landlord = User::find($invoice->landlord_id);
            $feeResult = $this->billingService->calculatePlatformFee($amount, $landlord);
            $this->billingService->recordPlatformFee($payment, $feeResult);

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
                    "Overpayment from M-Pesa payment #{$payment->id}",
                    $payment->id
                );
            }

            DB::commit();

            $invoice->load(['lease.tenant', 'lease.unit.building']);
            Mail::to($invoice->lease->tenant->email)->send(new PaymentReceived($payment, $invoice));
            PaymentReceivedEvent::dispatch($payment, $invoice);

            if (! empty($data['checkout_request_id'])) {
                MpesaPaymentStatusChanged::dispatch(
                    $data['checkout_request_id'],
                    'success',
                    $payment->id,
                    (float) $amount,
                    $receiptNumber,
                    'Payment received successfully'
                );
            }

            Log::info('M-Pesa payment recorded successfully', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'applied' => $appliedAmount,
                'overpayment_to_wallet' => $overpayment,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('M-Pesa payment processing failed', [
                'receipt' => $receiptNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function findInvoiceByReference(string $reference): ?Invoice
    {
        if (preg_match('/^INV[-\d]+$/i', $reference)) {
            return Invoice::where('invoice_number', $reference)->first();
        }

        if (preg_match('/^(\d+)$/i', $reference)) {
            return Invoice::find((int) $reference);
        }

        return Invoice::where('invoice_number', 'like', "%{$reference}%")->first();
    }

    protected function findInvoiceByCheckoutRequest(string $checkoutRequestId): ?Invoice
    {
        $payment = Payment::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

        return $payment?->invoice;
    }

    public function tillValidation(Request $request)
    {
        if (! $this->mpesaService->validateWebhookIP($request->ip())) {
            Log::warning('M-Pesa Till validation from unauthorized IP', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']);
        }

        $phone = $request->input('MSISDN');
        $amount = (float) $request->input('TransAmount');

        Log::info('M-Pesa Till validation request', [
            'amount' => $amount,
            'phone' => substr($phone, -4),
        ]);

        $tenant = $this->findTenantByPhone($phone);

        if (! $tenant) {
            Log::info('M-Pesa Till: No tenant found by phone, will queue for manual matching');

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted for manual review']);
        }

        $lease = $tenant->leases()->where('is_active', true)->first();
        if (! $lease) {
            Log::info('M-Pesa Till: No active lease for tenant', ['tenant_id' => $tenant->id]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted for manual review']);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function tillConfirmation(Request $request)
    {
        if (! $this->mpesaService->validateWebhookIP($request->ip())) {
            Log::warning('M-Pesa Till confirmation from unauthorized IP', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected']);
        }

        $phone = $request->input('MSISDN');
        $amount = (float) $request->input('TransAmount');
        $transId = $request->input('TransID');

        Log::info('M-Pesa Till confirmation received', [
            'transaction_id' => $transId,
            'amount' => $amount,
            'phone' => substr($phone, -4),
        ]);

        try {
            DB::beginTransaction();

            $existingPayment = Payment::where('mpesa_transaction_id', $transId)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                DB::rollBack();
                Log::info('M-Pesa Till payment already processed', ['receipt' => $transId]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
            }

            $tenant = $this->findTenantByPhone($phone);
            $invoice = null;

            if ($tenant) {
                $lease = $tenant->leases()->where('is_active', true)->first();
                $invoice = $lease?->invoices()
                    ->whereIn('status', ['sent', 'partial', 'overdue'])
                    ->orderBy('due_date', 'asc')
                    ->first();
            }

            if (! $invoice) {
                $this->queueUnmatchedPayment('mpesa_till', $transId, $amount, $request->all());
                DB::commit();

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Queued for matching']);
            }

            $this->processTillPayment($invoice, $amount, $transId, $phone, $request->all());
            DB::commit();

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Payment recorded']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('M-Pesa Till payment processing failed', [
                'receipt' => $transId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Error - will retry']);
        }
    }

    public function b2cResult(Request $request)
    {
        if (! $this->mpesaService->validateWebhookIP($request->ip())) {
            Log::warning('M-Pesa B2C result from unauthorized IP', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected']);
        }

        $result = $request->input('Result');
        $resultCode = $result['ResultCode'] ?? 1;
        $conversationId = $result['ConversationID'] ?? null;
        $transactionId = $result['TransactionID'] ?? null;

        Log::info('M-Pesa B2C result received', [
            'conversation_id' => $conversationId,
            'transaction_id' => $transactionId,
            'result_code' => $resultCode,
            'result_desc' => $result['ResultDesc'] ?? null,
        ]);

        if ($resultCode === 0) {
            $this->processB2CSuccess($result);
        } else {
            $this->processB2CFailure($result);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function b2cTimeout(Request $request)
    {
        if (! $this->mpesaService->validateWebhookIP($request->ip())) {
            Log::warning('M-Pesa B2C timeout from unauthorized IP', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected']);
        }

        Log::warning('M-Pesa B2C timeout received', [
            'payload' => $request->all(),
        ]);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    protected function findTenantByPhone(string $phone): ?User
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '254')) {
            $phone = '0'.substr($phone, 3);
        }

        return User::where('role', 'tenant')
            ->where(function ($query) use ($phone) {
                $query->where('mobile_number', $phone)
                    ->orWhere('mobile_number', '254'.substr($phone, 1))
                    ->orWhere('mobile_number', '+254'.substr($phone, 1));
            })
            ->first();
    }

    protected function processTillPayment(Invoice $invoice, float $amount, string $transId, string $phone, array $rawPayload): void
    {
        $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->first();

        $payment = $invoice->payments()->create([
            'landlord_id' => $invoice->landlord_id,
            'lease_id' => $invoice->lease_id,
            'amount' => $amount,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'reference' => 'MPESA-TILL-'.$transId,
            'mpesa_transaction_id' => $transId,
            'notes' => 'M-Pesa Till payment from '.$phone,
        ]);

        $landlord = User::find($invoice->landlord_id);
        $feeResult = $this->billingService->calculatePlatformFee($amount, $landlord);
        $this->billingService->recordPlatformFee($payment, $feeResult);

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
                "Overpayment from M-Pesa Till payment #{$payment->id}",
                $payment->id
            );
        }

        $invoice->load(['lease.tenant', 'lease.unit.building']);
        Mail::to($invoice->lease->tenant->email)->send(new PaymentReceived($payment, $invoice));
        PaymentReceivedEvent::dispatch($payment, $invoice);

        Log::info('M-Pesa Till payment recorded', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'applied' => $appliedAmount,
            'overpayment_to_wallet' => $overpayment,
        ]);
    }

    protected function queueUnmatchedPayment(string $source, string $transactionRef, float $amount, array $payload): void
    {
        Log::info('Queueing unmatched payment for manual reconciliation', [
            'source' => $source,
            'transaction_reference' => $transactionRef,
            'amount' => $amount,
        ]);
    }

    protected function processB2CSuccess(array $result): void
    {
        $conversationId = $result['ConversationID'] ?? null;
        $transactionId = $result['TransactionID'] ?? null;

        Log::info('M-Pesa B2C payment successful', [
            'conversation_id' => $conversationId,
            'transaction_id' => $transactionId,
        ]);
    }

    protected function processB2CFailure(array $result): void
    {
        $conversationId = $result['ConversationID'] ?? null;

        Log::error('M-Pesa B2C payment failed', [
            'conversation_id' => $conversationId,
            'result_code' => $result['ResultCode'] ?? null,
            'result_desc' => $result['ResultDesc'] ?? null,
        ]);
    }
}
