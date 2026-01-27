<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Events\PaymentReceived as PaymentReceivedEvent;
use App\Http\Controllers\Controller;
use App\Mail\PaymentReceived;
use App\Models\IntaSendTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\BillingModelService;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class IntaSendWebhookController extends Controller
{
    public function __construct(
        protected BillingModelService $billingService,
        protected ReceiptService $receiptService
    ) {}

    public function handleMpesaWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('IntaSend webhook received', [
            'api_ref' => $payload['api_ref'] ?? null,
            'invoice_id' => $payload['invoice_id'] ?? null,
            'state' => $payload['state'] ?? null,
        ]);

        $apiRef = $payload['api_ref'] ?? null;
        $intasendInvoiceId = $payload['invoice_id'] ?? null;

        $transaction = $this->findTransaction($apiRef, $intasendInvoiceId);

        if (! $transaction) {
            Log::warning('IntaSend webhook: Transaction not found', [
                'api_ref' => $apiRef,
                'intasend_invoice_id' => $intasendInvoiceId,
            ]);

            return response()->json(['status' => 'ok', 'message' => 'Transaction not found']);
        }

        if (! $this->validateChallenge($payload, $transaction)) {
            Log::warning('IntaSend webhook: Challenge validation failed', [
                'api_ref' => $apiRef,
                'landlord_id' => $transaction->landlord_id,
            ]);

            return response()->json(['status' => 'ok', 'message' => 'Challenge validation failed']);
        }

        $state = strtoupper($payload['state'] ?? '');

        $transaction->update(['webhook_payload' => $payload]);

        return match ($state) {
            IntaSendTransaction::STATE_COMPLETE => $this->processCompletePayment($payload, $transaction),
            IntaSendTransaction::STATE_FAILED => $this->handleFailedPayment($payload, $transaction),
            default => $this->handlePendingOrProcessing($payload, $transaction, $state),
        };
    }

    protected function findTransaction(?string $apiRef, ?string $intasendInvoiceId): ?IntaSendTransaction
    {
        if ($apiRef) {
            $transaction = IntaSendTransaction::where('api_ref', $apiRef)->first();
            if ($transaction) {
                return $transaction;
            }
        }

        if ($intasendInvoiceId) {
            return IntaSendTransaction::where('intasend_invoice_id', $intasendInvoiceId)->first();
        }

        return null;
    }

    protected function validateChallenge(array $payload, IntaSendTransaction $transaction): bool
    {
        $receivedChallenge = $payload['challenge'] ?? '';

        $config = PaymentConfiguration::where('landlord_id', $transaction->landlord_id)->first();

        if (! $config || empty($config->intasend_webhook_challenge)) {
            Log::warning('IntaSend webhook: No challenge configured', [
                'landlord_id' => $transaction->landlord_id,
            ]);

            return false;
        }

        return hash_equals($config->intasend_webhook_challenge, $receivedChallenge);
    }

    protected function handlePendingOrProcessing(array $payload, IntaSendTransaction $transaction, string $state): JsonResponse
    {
        if ($state === IntaSendTransaction::STATE_PROCESSING) {
            $transaction->markProcessing();
        }

        Log::info('IntaSend webhook: State updated', [
            'api_ref' => $transaction->api_ref,
            'state' => $state,
        ]);

        return response()->json(['status' => 'success', 'message' => 'State updated']);
    }

    protected function handleFailedPayment(array $payload, IntaSendTransaction $transaction): JsonResponse
    {
        $failureReason = $payload['failed_reason'] ?? 'Unknown failure';

        $transaction->markFailed($failureReason);

        Log::info('IntaSend webhook: Payment failed', [
            'api_ref' => $transaction->api_ref,
            'reason' => $failureReason,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Failure recorded']);
    }

    protected function processCompletePayment(array $payload, IntaSendTransaction $transaction): JsonResponse
    {
        $mpesaReceipt = $payload['mpesa_reference'] ?? $payload['invoice_id'] ?? '';
        $amount = (float) ($payload['value'] ?? $transaction->amount);

        try {
            DB::beginTransaction();

            $transaction = IntaSendTransaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->first();

            if ($transaction->payment_id !== null) {
                DB::rollBack();
                Log::info('IntaSend payment already processed', [
                    'api_ref' => $transaction->api_ref,
                    'payment_id' => $transaction->payment_id,
                ]);

                return response()->json(['status' => 'success', 'message' => 'Already processed']);
            }

            $existingPayment = Payment::where('intasend_reference', $transaction->api_ref)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                DB::rollBack();
                Log::info('IntaSend payment found by reference', [
                    'api_ref' => $transaction->api_ref,
                    'payment_id' => $existingPayment->id,
                ]);

                return response()->json(['status' => 'success', 'message' => 'Already processed']);
            }

            $invoice = Invoice::where('id', $transaction->invoice_id)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                DB::rollBack();
                Log::error('IntaSend webhook: Invoice not found', [
                    'api_ref' => $transaction->api_ref,
                    'invoice_id' => $transaction->invoice_id,
                ]);

                return response()->json(['status' => 'error', 'message' => 'Invoice not found']);
            }

            $payment = $invoice->payments()->create([
                'landlord_id' => $invoice->landlord_id,
                'lease_id' => $invoice->lease_id,
                'amount' => $amount,
                'payment_method' => 'mobile_money',
                'payment_date' => now(),
                'reference' => 'INTASEND-'.$mpesaReceipt,
                'intasend_transaction_id' => $transaction->intasend_invoice_id,
                'intasend_reference' => $transaction->api_ref,
                'notes' => 'IntaSend M-Pesa payment from '.($transaction->phone_number ? substr($transaction->phone_number, -4) : 'unknown'),
            ]);

            $transaction->update([
                'payment_id' => $payment->id,
                'state' => IntaSendTransaction::STATE_COMPLETE,
                'mpesa_receipt' => $mpesaReceipt,
                'intasend_charges' => (float) ($payload['charges'] ?? 0),
                'net_amount' => (float) ($payload['net_amount'] ?? $amount),
            ]);

            $landlord = User::find($invoice->landlord_id);
            $feeResult = $this->billingService->calculatePlatformFee($amount, $landlord);
            $this->billingService->recordPlatformFee($payment, $feeResult);

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
                    "Overpayment from IntaSend payment #{$payment->id}",
                    $payment->id
                );
            }

            DB::commit();

            $invoice->load(['lease.tenant', 'lease.unit.building']);
            Mail::to($invoice->lease->tenant->email)->queue(new PaymentReceived($payment, $invoice));
            PaymentReceivedEvent::dispatch($payment, $invoice);

            Log::info('IntaSend payment recorded successfully', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'applied' => $appliedAmount,
                'overpayment_to_wallet' => $overpayment,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Payment recorded']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('IntaSend payment processing failed', [
                'api_ref' => $transaction->api_ref,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Processing failed']);
        }
    }
}
