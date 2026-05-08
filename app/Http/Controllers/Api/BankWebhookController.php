<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Mail\PaymentReceived;
use App\Models\BankReconciliationQueue;
use App\Models\BankWebhookLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Banking\BankServiceInterface;
use App\Services\Banking\CoopBankService;
use App\Services\Banking\EquityBankService;
use App\Services\Banking\KcbBankService;
use App\Services\Banking\PaymentNotification;
use App\Services\BillingModelService;
use App\Services\IdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BankWebhookController extends Controller
{
    public function __construct(
        protected BillingModelService $billingService,
        protected IdempotencyService $idempotencyService
    ) {}

    public function equityWebhook(Request $request, EquityBankService $service)
    {
        return $this->processWebhook($request, $service, 'equity');
    }

    public function kcbWebhook(Request $request, KcbBankService $service)
    {
        return $this->processWebhook($request, $service, 'kcb');
    }

    public function coopWebhook(Request $request, CoopBankService $service)
    {
        return $this->processWebhook($request, $service, 'coop');
    }

    private function processWebhook(Request $request, BankServiceInterface $service, string $bankCode)
    {
        $log = BankWebhookLog::create([
            'bank_code' => $bankCode,
            'payload' => $request->all(),
            'status' => 'received',
            'ip_address' => $request->ip(),
        ]);

        $idempotencyKey = null;

        try {
            $signature = $request->header('X-Signature') ?? $request->header('Authorization') ?? '';
            if (! $service->validateWebhook($signature, $request->getContent())) {
                $log->markAsError('Invalid signature');
                Log::warning("{$bankCode} webhook: Invalid signature", ['ip' => $request->ip()]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $log->markAsProcessing();
            $notification = $service->parsePaymentNotification($request->all());

            $idempotencyKey = "bank:{$bankCode}:{$notification->transactionId}";
            $idempotencyResult = $this->idempotencyService->acquire($idempotencyKey);

            if (! $idempotencyResult['acquired']) {
                Log::info('Bank payment already handled (idempotency)', [
                    'key' => $idempotencyKey,
                    'cached' => $idempotencyResult['response'] !== null,
                ]);

                return response()->json(['status' => 'success']);
            }

            $payment = $this->matchAndRecordPayment($notification);

            $this->idempotencyService->release($idempotencyKey, [
                'status' => $payment ? 'success' : 'unmatched',
                'payment_id' => $payment?->id,
            ]);

            $log->markAsSuccess($payment);

            return response()->json(['status' => 'success']);
        } catch (\Illuminate\Database\QueryException $e) {
            // MySQL error 1062 = duplicate entry (unique constraint violation)
            if ($e->errorInfo[1] === 1062) {
                if ($idempotencyKey) {
                    $this->idempotencyService->release($idempotencyKey, ['status' => 'duplicate']);
                }
                Log::info('Bank duplicate webhook ignored (idempotent)', [
                    'bank_code' => $bankCode,
                ]);

                return response()->json(['status' => 'success']);
            }

            if ($idempotencyKey) {
                $this->idempotencyService->fail($idempotencyKey, $e->getMessage());
            }
            $log->markAsError($e->getMessage());
            Log::error("{$bankCode} webhook database error", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            if ($idempotencyKey) {
                $this->idempotencyService->fail($idempotencyKey, $e->getMessage());
            }
            $log->markAsError($e->getMessage());
            Log::error("{$bankCode} webhook processing failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            BankReconciliationQueue::create([
                'bank_code' => $bankCode,
                'transaction_reference' => $request->input('transactionId')
                    ?? $request->input('TransactionID')
                    ?? $request->input('MessageReference')
                    ?? 'unknown',
                'amount' => (float) ($request->input('amount') ?? $request->input('Amount') ?? 0),
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'raw_payload' => $request->all(),
                'next_retry_at' => now()->addMinutes(5),
            ]);

            return response()->json(['status' => 'queued'], 202);
        }
    }

    private function matchAndRecordPayment(PaymentNotification $notification): ?Payment
    {
        return DB::transaction(function () use ($notification) {
            $existing = Payment::where('bank_transaction_id', $notification->transactionId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $invoice = $this->findInvoice($notification);

            if (! $invoice) {
                BankReconciliationQueue::create([
                    'bank_code' => $notification->bankCode,
                    'transaction_reference' => $notification->transactionId,
                    'amount' => $notification->amount,
                    'status' => 'unmatched',
                    'raw_payload' => $notification->rawPayload,
                ]);

                return null;
            }

            $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->first();
            $amount = $notification->amount;

            $payment = $invoice->payments()->create([
                'landlord_id' => $invoice->landlord_id,
                'lease_id' => $invoice->lease_id,
                'amount' => $amount,
                'payment_method' => 'bank_transfer',
                'payment_date' => $notification->transactionDate,
                'reference' => $notification->reference ?? $notification->transactionId,
                'bank_code' => $notification->bankCode,
                'bank_transaction_id' => $notification->transactionId,
                'bank_transaction_date' => $notification->transactionDate,
                'bank_account_number' => $notification->accountNumber,
                'bank_reference' => $notification->reference,
                'reconciliation_status' => 'matched',
                'reconciliation_matched_at' => now(),
                'notes' => 'Bank transfer from '.($notification->senderName ?? 'unknown'),
            ]);

            $landlord = User::find($invoice->landlord_id);
            $feeResult = $this->billingService->calculatePlatformFee($amount, $landlord);
            $this->billingService->recordPlatformFee($payment, $feeResult);

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
                    "Overpayment from bank transfer #{$payment->id}",
                    $payment->id
                );
            }

            $invoice->load(['lease.tenant', 'lease.unit.building']);
            Mail::to($invoice->lease->tenant->email)->queue(new PaymentReceived($payment, $invoice));

            Log::info('Bank payment recorded', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'bank' => $notification->bankCode,
                'amount' => $amount,
            ]);

            return $payment;
        });
    }

    private function findInvoice(PaymentNotification $notification): ?Invoice
    {
        if ($notification->reference) {
            $invoice = Invoice::where('invoice_number', $notification->reference)->first();
            if ($invoice) {
                return $invoice;
            }
        }

        if ($notification->senderPhone) {
            $phone = preg_replace('/[^0-9]/', '', $notification->senderPhone);
            if (str_starts_with($phone, '254')) {
                $phone = '0'.substr($phone, 3);
            }

            $tenant = User::where('role', 'tenant')
                ->where(function ($query) use ($phone) {
                    $query->where('mobile_number', $phone)
                        ->orWhere('mobile_number', '254'.substr($phone, 1))
                        ->orWhere('mobile_number', '+254'.substr($phone, 1));
                })
                ->first();

            if ($tenant) {
                $lease = $tenant->leases()->where('is_active', true)->first();

                return $lease?->invoices()
                    ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
                    ->orderBy('due_date', 'asc')
                    ->first();
            }
        }

        return null;
    }
}
