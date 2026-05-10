<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Events\MpesaPaymentStatusChanged;
use App\Events\PaymentReceived as PaymentReceivedEvent;
use App\Http\Controllers\Controller;
use App\Mail\OverpaymentNotification;
use App\Mail\PaymentReceived;
use App\Models\BankReconciliationQueue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use App\Models\WebhookLog;
use App\Services\BillingModelService;
use App\Services\IdempotencyService;
use App\Services\MetricsService;
use App\Services\Payment\WebhookDeadLetterService;
use App\Services\Payment\WebhookLogService;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MpesaWebhookController extends Controller
{
    private const AMOUNT_TOLERANCE_KES = 1.00;

    public function __construct(
        protected BillingModelService $billingService,
        protected ReceiptService $receiptService,
        protected IdempotencyService $idempotencyService,
        protected WebhookDeadLetterService $deadLetterService,
        protected WebhookLogService $webhookLogService
    ) {}

    public function stkCallback(Request $request)
    {
        $callback = $request->input('Body.stkCallback');

        if (! $callback) {
            // OBS-11: malformed-payload counter is the canary for misrouted
            // webhooks (Safaricom retried the wrong endpoint, NAT mangled
            // the body, etc.).
            app(MetricsService::class)->increment(
                'webhook.received',
                labels: ['provider' => 'mpesa', 'event' => 'stk_callback', 'outcome' => 'invalid_payload']
            );

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
        }

        app(MetricsService::class)->increment(
            'webhook.received',
            labels: ['provider' => 'mpesa', 'event' => 'stk_callback', 'outcome' => ($callback['ResultCode'] ?? -1) === 0 ? 'success' : 'failure']
        );

        $eventId = $callback['CheckoutRequestID'] ?? 'stk-unknown-'.bin2hex(random_bytes(8));
        $webhookLog = $this->webhookLogService->recordHit(
            WebhookLog::PROVIDER_MPESA,
            $eventId,
            'stk_callback',
            json_encode($request->all()),
            null,
            $request->ip()
        );
        $this->webhookLogService->startTiming($eventId);

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

            $this->webhookLogService->finishTiming($webhookLog, $eventId, WebhookLog::STATUS_PROCESSED);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $items = collect($callback['CallbackMetadata']['Item'] ?? [])
            ->mapWithKeys(fn ($item) => [$item['Name'] => $item['Value'] ?? null]);

        $mpesaReceiptNumber = $items->get('MpesaReceiptNumber');
        $amount = $items->get('Amount');
        $phone = $items->get('PhoneNumber');
        $transactionDate = $items->get('TransactionDate');

        if (! $mpesaReceiptNumber || ! $amount) {
            // HANDLE-2: schema-mismatch on the success branch. We accept the
            // 200 (otherwise Daraja keeps retrying) but capture the payload
            // to the dead-letter queue so an operator can match the payment
            // by hand. Landlord context comes from the pending Payment row
            // we created at STK-push time, keyed by CheckoutRequestID.
            $landlordId = null;
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
            if ($checkoutRequestId) {
                $pending = Payment::withoutGlobalScope('landlord')
                    ->where('mpesa_checkout_request_id', $checkoutRequestId)
                    ->select(['landlord_id'])
                    ->first();
                $landlordId = $pending?->landlord_id;
            }

            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_MPESA,
                'stk_callback',
                $callback,
                'Missing MpesaReceiptNumber or Amount in callback metadata',
                WebhookDeadLetter::ERROR_SCHEMA,
                $landlordId,
                $request->headers->all(),
            );

            Log::error('M-Pesa STK callback missing required data', ['items' => $items->toArray()]);
            $this->webhookLogService->finishTiming($webhookLog, $eventId, WebhookLog::STATUS_FAILED);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $this->processPayment([
            'checkout_request_id' => $callback['CheckoutRequestID'],
            'mpesa_receipt_number' => $mpesaReceiptNumber,
            'amount' => $amount,
            'phone' => $phone,
            'transaction_date' => $transactionDate,
        ]);

        $this->webhookLogService->finishTiming($webhookLog, $eventId, WebhookLog::STATUS_PROCESSED);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function c2bValidation(Request $request)
    {
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
        $transId = $request->input('TransID');
        $webhookLog = $this->webhookLogService->recordHit(
            WebhookLog::PROVIDER_MPESA,
            $transId,
            'c2b_confirmation',
            json_encode($request->all()),
            null,
            $request->ip()
        );
        $this->webhookLogService->startTiming($transId);

        Log::info('M-Pesa C2B confirmation received', [
            'transaction_id' => $transId,
            'amount' => $request->input('TransAmount'),
            'account_reference' => $request->input('BillRefNumber'),
        ]);

        $this->processPayment([
            'mpesa_receipt_number' => $transId,
            'amount' => $request->input('TransAmount'),
            'phone' => $request->input('MSISDN'),
            'account_reference' => $request->input('BillRefNumber'),
            'transaction_date' => $request->input('TransTime'),
            'first_name' => $request->input('FirstName'),
            'last_name' => $request->input('LastName'),
        ]);

        $this->webhookLogService->finishTiming($webhookLog, $transId, WebhookLog::STATUS_PROCESSED);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    protected function processPayment(array $data): void
    {
        $receiptNumber = $data['mpesa_receipt_number'];
        $idempotencyKey = "mpesa:{$receiptNumber}";

        $idempotencyResult = $this->idempotencyService->acquire($idempotencyKey);

        if (! $idempotencyResult['acquired']) {
            Log::info('M-Pesa payment already handled (idempotency)', [
                'key' => $idempotencyKey,
                'cached' => $idempotencyResult['response'] !== null,
            ]);

            return;
        }

        try {
            DB::beginTransaction();

            $existingPayment = Payment::where('mpesa_transaction_id', $receiptNumber)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                DB::rollBack();
                $this->idempotencyService->release($idempotencyKey, [
                    'status' => 'duplicate',
                    'payment_id' => $existingPayment->id,
                ]);
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
                $this->idempotencyService->release($idempotencyKey, [
                    'status' => 'no_invoice',
                    'message' => 'No matching invoice found',
                ]);
                Log::warning('M-Pesa payment: No matching invoice found', $data);

                return;
            }

            $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->first();
            $amount = (float) $data['amount'];

            $needsReconciliation = false;
            if (! empty($data['checkout_request_id'])) {
                $expectedAmount = $invoice->getOutstandingAmount();
                $difference = abs($amount - $expectedAmount);

                if ($difference > self::AMOUNT_TOLERANCE_KES) {
                    // Log the mismatch but still record the payment - real funds received should not be discarded
                    $this->captureAmountMismatch($amount, $expectedAmount, $invoice, $data);
                    $needsReconciliation = true;
                }
            }

            $paymentNotes = 'M-Pesa payment from '.($data['phone'] ?? 'unknown');
            if ($needsReconciliation) {
                $paymentNotes .= ' [NEEDS RECONCILIATION: Amount mismatch - expected '.$expectedAmount.', received '.$amount.']';
            }

            $payment = $invoice->payments()->create([
                'landlord_id' => $invoice->landlord_id,
                'lease_id' => $invoice->lease_id,
                'amount' => $amount,
                'payment_method' => 'mobile_money',
                'payment_date' => now(),
                'reference' => 'MPESA-'.$receiptNumber,
                'mpesa_transaction_id' => $receiptNumber,
                'mpesa_checkout_request_id' => $data['checkout_request_id'] ?? null,
                'notes' => $paymentNotes,
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
            $newStatus = $newAmountPaid >= $invoice->total_due ? InvoiceStatus::Paid : InvoiceStatus::Partial;

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
                $invoice->lease->refresh();
            }

            DB::commit();

            $invoice->load(['lease.tenant', 'lease.unit.building']);

            if ($invoice->lease?->tenant?->email && filter_var($invoice->lease->tenant->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($invoice->lease->tenant->email)->queue(new PaymentReceived($payment, $invoice));
            } else {
                Log::warning('M-Pesa webhook: Cannot send payment receipt - tenant email missing', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'lease_id' => $invoice->lease_id,
                ]);
            }

            if ($overpayment > 0 && $invoice->lease) {
                $tenant = $invoice->lease->tenant;
                if ($landlord && $tenant && filter_var($landlord->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($landlord->email)->queue(new OverpaymentNotification(
                        $payment,
                        $invoice->lease,
                        $tenant,
                        $overpayment,
                        $invoice->lease->wallet_balance
                    ));
                }
            }

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

            $this->idempotencyService->release($idempotencyKey, [
                'status' => 'success',
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            // MySQL error 1062 = duplicate entry (unique constraint violation)
            // This is expected idempotent behavior - not an error condition
            if ($e->errorInfo[1] === 1062) {
                $this->idempotencyService->release($idempotencyKey, ['status' => 'duplicate']);
                Log::info('M-Pesa duplicate webhook ignored (idempotent)', [
                    'mpesa_transaction_id' => $receiptNumber,
                ]);

                return;
            }

            // Other database errors are real failures
            $this->idempotencyService->fail($idempotencyKey, $e->getMessage());
            Log::error('M-Pesa payment database error', [
                'receipt' => $receiptNumber,
                'error' => $e->getMessage(),
            ]);

            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_MPESA,
                'stk_callback',
                $data,
                $e->getMessage(),
                WebhookDeadLetter::ERROR_TRANSIENT,
                $invoice?->landlord_id
            );
        } catch (\Exception $e) {
            DB::rollBack();
            $this->idempotencyService->fail($idempotencyKey, $e->getMessage());
            Log::error('M-Pesa payment processing failed', [
                'receipt' => $receiptNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_MPESA,
                'stk_callback',
                $data,
                $e->getMessage(),
                WebhookDeadLetter::ERROR_PERMANENT,
                $invoice?->landlord_id
            );
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

    private function captureAmountMismatch(float $received, float $expected, Invoice $invoice, array $callbackData): void
    {
        Log::warning('M-Pesa STK amount mismatch', [
            'expected' => $expected,
            'received' => $received,
            'difference' => abs($received - $expected),
            'invoice_id' => $invoice->id,
            'checkout_request_id' => $callbackData['checkout_request_id'] ?? 'unknown',
        ]);

        $this->deadLetterService->capture(
            WebhookDeadLetter::PROVIDER_MPESA,
            'stk_callback',
            $callbackData,
            "Amount mismatch: expected {$expected}, received {$received}",
            WebhookDeadLetter::ERROR_SCHEMA,
            $invoice->landlord_id
        );
    }

    public function tillValidation(Request $request)
    {
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
        $phone = $request->input('MSISDN');
        $amount = (float) $request->input('TransAmount');
        $transId = $request->input('TransID');

        $webhookLog = $this->webhookLogService->recordHit(
            WebhookLog::PROVIDER_MPESA,
            $transId,
            'till_confirmation',
            json_encode($request->all()),
            null,
            $request->ip()
        );
        $this->webhookLogService->startTiming($transId);

        $idempotencyKey = "mpesa_till:{$transId}";

        Log::info('M-Pesa Till confirmation received', [
            'transaction_id' => $transId,
            'amount' => $amount,
            'phone' => substr($phone, -4),
        ]);

        $idempotencyResult = $this->idempotencyService->acquire($idempotencyKey);

        if (! $idempotencyResult['acquired']) {
            Log::info('M-Pesa Till payment already handled (idempotency)', [
                'key' => $idempotencyKey,
                'cached' => $idempotencyResult['response'] !== null,
            ]);
            $this->webhookLogService->finishTiming($webhookLog, $transId, WebhookLog::STATUS_PROCESSED);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
        }

        try {
            DB::beginTransaction();

            $existingPayment = Payment::where('mpesa_transaction_id', $transId)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                DB::rollBack();
                $this->idempotencyService->release($idempotencyKey, [
                    'status' => 'duplicate',
                    'payment_id' => $existingPayment->id,
                ]);
                Log::info('M-Pesa Till payment already processed', ['receipt' => $transId]);
                $this->webhookLogService->finishTiming($webhookLog, $transId, WebhookLog::STATUS_PROCESSED);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
            }

            $tenant = $this->findTenantByPhone($phone);
            $invoice = null;

            if ($tenant) {
                $lease = $tenant->leases()->where('is_active', true)->first();
                $invoice = $lease?->invoices()
                    ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
                    ->orderBy('due_date', 'asc')
                    ->first();
            }

            if (! $invoice) {
                $this->queueUnmatchedPayment(
                    'mpesa_till',
                    $transId,
                    $amount,
                    $request->all(),
                    $tenant?->landlord_id,
                );
                DB::commit();
                $this->idempotencyService->release($idempotencyKey, [
                    'status' => 'queued_for_matching',
                ]);
                $this->webhookLogService->finishTiming($webhookLog, $transId, WebhookLog::STATUS_PROCESSED);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Queued for matching']);
            }

            $this->processTillPayment($invoice, $amount, $transId, $phone, $request->all());
            DB::commit();
            $this->idempotencyService->release($idempotencyKey, [
                'status' => 'success',
                'invoice_id' => $invoice->id,
            ]);
            $this->webhookLogService->finishTiming($webhookLog, $transId, WebhookLog::STATUS_PROCESSED);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Payment recorded']);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            // MySQL error 1062 = duplicate entry (unique constraint violation)
            // This is expected idempotent behavior - not an error condition
            if ($e->errorInfo[1] === 1062) {
                $this->idempotencyService->release($idempotencyKey, ['status' => 'duplicate']);
                Log::info('M-Pesa Till duplicate webhook ignored (idempotent)', [
                    'mpesa_transaction_id' => $transId,
                ]);
                $this->webhookLogService->finishTiming($webhookLog, $transId, WebhookLog::STATUS_PROCESSED);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
            }

            $this->idempotencyService->fail($idempotencyKey, $e->getMessage());
            Log::error('M-Pesa Till payment database error', [
                'receipt' => $transId,
                'error' => $e->getMessage(),
            ]);

            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_MPESA,
                'till_confirmation',
                $request->all(),
                $e->getMessage(),
                WebhookDeadLetter::ERROR_TRANSIENT,
                $invoice?->landlord_id
            );
            $this->webhookLogService->finishTiming($webhookLog, $transId, WebhookLog::STATUS_FAILED);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Error - will retry']);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->idempotencyService->fail($idempotencyKey, $e->getMessage());
            Log::error('M-Pesa Till payment processing failed', [
                'receipt' => $transId,
                'error' => $e->getMessage(),
            ]);

            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_MPESA,
                'till_confirmation',
                $request->all(),
                $e->getMessage(),
                WebhookDeadLetter::ERROR_PERMANENT,
                $invoice?->landlord_id
            );
            $this->webhookLogService->finishTiming($webhookLog, $transId, WebhookLog::STATUS_FAILED);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Error - will retry']);
        }
    }

    public function b2cResult(Request $request)
    {
        $result = $request->input('Result');
        $resultCode = $result['ResultCode'] ?? 1;
        $conversationId = $result['ConversationID'] ?? null;
        $transactionId = $result['TransactionID'] ?? null;
        $eventId = $transactionId ?? $conversationId ?? 'b2c-unknown-'.bin2hex(random_bytes(8));

        $webhookLog = $this->webhookLogService->recordHit(
            WebhookLog::PROVIDER_MPESA,
            $eventId,
            'b2c_result',
            json_encode($request->all()),
            null,
            $request->ip()
        );
        $this->webhookLogService->startTiming($eventId);

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

        $this->webhookLogService->finishTiming($webhookLog, $eventId, WebhookLog::STATUS_PROCESSED);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function b2cTimeout(Request $request)
    {
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
        $newStatus = $newAmountPaid >= $invoice->total_due ? InvoiceStatus::Paid : InvoiceStatus::Partial;

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
            $invoice->lease->refresh();
        }

        $invoice->load(['lease.tenant', 'lease.unit.building']);

        if ($invoice->lease?->tenant?->email && filter_var($invoice->lease->tenant->email, FILTER_VALIDATE_EMAIL)) {
            Mail::to($invoice->lease->tenant->email)->queue(new PaymentReceived($payment, $invoice));
        } else {
            Log::warning('M-Pesa Till webhook: Cannot send payment receipt - tenant email missing', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'lease_id' => $invoice->lease_id,
            ]);
        }

        if ($overpayment > 0 && $invoice->lease) {
            $tenant = $invoice->lease->tenant;
            if ($landlord && $tenant && filter_var($landlord->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($landlord->email)->queue(new OverpaymentNotification(
                    $payment,
                    $invoice->lease,
                    $tenant,
                    $overpayment,
                    $invoice->lease->wallet_balance
                ));
            }
        }

        // CONC-3: defer broadcast until COMMIT. processTillPayment is called
        // inside the surrounding DB::beginTransaction (tillConfirmation handler);
        // dispatching the broadcast event before commit lets a worker observe
        // pre-commit state.
        DB::afterCommit(fn () => PaymentReceivedEvent::dispatch($payment, $invoice));

        Log::info('M-Pesa Till payment recorded', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'applied' => $appliedAmount,
            'overpayment_to_wallet' => $overpayment,
        ]);
    }

    protected function queueUnmatchedPayment(string $source, string $transactionRef, float $amount, array $payload, ?int $landlordId = null): void
    {
        Log::info('Queueing unmatched payment for manual reconciliation', [
            'source' => $source,
            'transaction_reference' => $transactionRef,
            'amount' => $amount,
            'landlord_id' => $landlordId,
        ]);

        // HANDLE-3: persist a row so an operator can reconcile later. Without
        // this the payment was only visible in app logs, not the
        // reconciliation queue UI. landlord_id is required by the schema —
        // when we can't derive one (no tenant match), capture the payload to
        // the webhook DLQ instead so it's still visible in ops dashboards.
        if (! $landlordId) {
            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_MPESA,
                $source,
                $payload,
                'Unmatched payment with no derivable landlord',
                WebhookDeadLetter::ERROR_SCHEMA,
                null,
            );

            return;
        }

        BankReconciliationQueue::withoutGlobalScope('landlord')->create([
            'landlord_id' => $landlordId,
            'bank_code' => $source,
            'transaction_reference' => $transactionRef,
            'amount' => $amount,
            'status' => 'unmatched',
            'raw_payload' => $payload,
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

        // HANDLE-3: when a Refund is awaiting B2C confirmation, mark it
        // completed so the operator UI doesn't show 'processing' forever.
        if ($conversationId) {
            $refund = Refund::withoutGlobalScope('landlord')
                ->where('mpesa_conversation_id', $conversationId)
                ->first();
            if ($refund) {
                $refund->markAsCompleted($transactionId);
            }
        }
    }

    protected function processB2CFailure(array $result): void
    {
        $conversationId = $result['ConversationID'] ?? null;

        Log::error('M-Pesa B2C payment failed', [
            'conversation_id' => $conversationId,
            'result_code' => $result['ResultCode'] ?? null,
            'result_desc' => $result['ResultDesc'] ?? null,
        ]);

        if ($conversationId) {
            $refund = Refund::withoutGlobalScope('landlord')
                ->where('mpesa_conversation_id', $conversationId)
                ->first();
            if ($refund) {
                $refund->markAsFailed([
                    'result_code' => $result['ResultCode'] ?? null,
                    'result_desc' => $result['ResultDesc'] ?? null,
                ]);
            }
        }
    }
}
