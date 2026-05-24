<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Mail\PaymentReceived;
use App\Models\BankReconciliationQueue;
use App\Models\BankWebhookLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\Banking\BankServiceInterface;
use App\Services\Banking\CoopBankService;
use App\Services\Banking\EquityBankService;
use App\Services\Banking\FamilyBankService;
use App\Services\Banking\KcbBankService;
use App\Services\Banking\PaymentNotification;
use App\Services\Banking\PostBankService;
use App\Services\BillingModelService;
use App\Services\IdempotencyService;
use App\Services\IncidentDetector;
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

    public function postBankWebhook(Request $request, PostBankService $service)
    {
        return $this->processWebhook($request, $service, 'postbank');
    }

    public function familyBankWebhook(Request $request, FamilyBankService $service)
    {
        return $this->processWebhook($request, $service, 'familybank');
    }

    private function processWebhook(Request $request, BankServiceInterface $service, string $bankCode)
    {
        $log = BankWebhookLog::create([
            'bank_code' => $bankCode,
            'payload' => $request->all(),
            'status' => 'received',
            'ip_address' => $request->ip(),
            // Phase-21 DEFER-OBSERV-1: cross-log correlation. logs:correlate
            // ties this webhook back to the originating request via the
            // X-Request-Id header stamped by Phase-14 AddRequestId middleware.
            'request_id' => $request->header('X-Request-Id'),
        ]);

        $idempotencyKey = null;

        try {
            $signature = $request->header('X-Signature') ?? $request->header('Authorization') ?? '';

            // CRYPTO-11: resolve the landlord owning the destination
            // account BEFORE signature validation so we can validate
            // with that landlord's secret. parsePaymentNotification is
            // pure data mapping (no exec), safe on unverified input —
            // we wrap it because malformed payloads will throw and
            // should be treated as bad-sig (401).
            $notification = null;
            $perLandlordSecret = null;
            try {
                $notification = $service->parsePaymentNotification($request->all());
                $landlordId = $this->resolveLandlordFromBankAccount($notification);
                if ($landlordId) {
                    $perLandlordSecret = PaymentConfiguration::webhookSecretFor($landlordId, $bankCode);
                }
            } catch (\Throwable $parseError) {
                // Fall through to validation with env-only secret; an
                // adversary cannot use a malformed payload to opt out
                // of validation because the env secret is still
                // required when no landlord resolves.
                $notification = null;
            }

            if (! $service->validateWebhook($signature, $request->getContent(), $perLandlordSecret)) {
                $log->markAsError('Invalid signature');
                Log::warning("{$bankCode} webhook: Invalid signature", ['ip' => $request->ip()]);

                // Phase-13 BREACH-5: record a SecurityLog row + run the
                // IncidentDetector. The webhook_signature_failed event_type
                // is what IncidentDetector::checkWebhookSignatureFlood
                // counts; 10+ failures from one IP in 1 minute escalates
                // to a SecurityIncident. Wrapped so an error in detection
                // never escapes the controller.
                try {
                    SecurityLog::create([
                        'user_id' => null,
                        'landlord_id' => null,
                        'event_type' => 'webhook_signature_failed',
                        'severity' => SecurityLog::SEVERITY_WARNING,
                        'description' => "{$bankCode} webhook signature validation failed",
                        'ip_address' => $request->ip(),
                        'metadata' => ['bank_code' => $bankCode],
                        'is_suspicious' => true,
                    ]);
                    app(IncidentDetector::class)->checkWebhookSignatureFlood((string) $request->ip());
                } catch (\Throwable $e) {
                    Log::warning('IncidentDetector failed during webhook-signature escalation', [
                        'error' => $e->getMessage(),
                    ]);
                }

                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $log->markAsProcessing();
            $notification ??= $service->parsePaymentNotification($request->all());

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

            // Phase-98: a water-client invoice has no lease/wallet — never deref it.
            if ($overpayment > 0 && $invoice->lease) {
                $invoice->lease->creditToWallet(
                    $overpayment,
                    "Overpayment from bank transfer #{$payment->id}",
                    $payment->id
                );
            } elseif ($overpayment > 0) {
                Log::warning('Water-client invoice overpaid via bank transfer; no wallet to absorb it', [
                    'invoice_id' => $invoice->id,
                    'water_connection_id' => $invoice->water_connection_id,
                    'payment_id' => $payment->id,
                    'overpayment' => $overpayment,
                ]);
            }

            $invoice->load(['lease.tenant', 'lease.unit.building']);
            $recipientEmail = $invoice->recipientUser()?->email;
            if ($recipientEmail) {
                Mail::to($recipientEmail)->queue(new PaymentReceived($payment, $invoice));
            } else {
                Log::warning('Skipping bank payment receipt email: recipient not found', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                ]);
            }

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
        // PRIV-5: scope every lookup by the landlord that owns the
        // receiving bank account. Without this, an invoice_number that
        // collides across landlords (the default INV-0001 template
        // collides immediately) gets credited to whichever row sorts
        // first. If we can't resolve a landlord from the receiving
        // account, refuse to match — caller queues to DLQ.
        $landlordId = $this->resolveLandlordFromBankAccount($notification);
        if (! $landlordId) {
            return null;
        }

        if ($notification->reference) {
            $invoice = Invoice::where('landlord_id', $landlordId)
                ->where('invoice_number', $notification->reference)
                ->first();
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
                ->where('landlord_id', $landlordId)
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

    /**
     * PRIV-5: resolve the landlord whose configured bank account is the
     * receiving account on this webhook. The encrypted column means we
     * can't index-search; instead iterate landlord configs that have
     * this bank configured and decrypt-compare. Acceptable cost — bank
     * webhook volume is low and we cache the small landlord-config set.
     */
    private function resolveLandlordFromBankAccount(PaymentNotification $notification): ?int
    {
        if (! $notification->accountNumber) {
            return null;
        }

        $configs = \App\Models\PaymentConfiguration::query()
            ->where('bank_name', '!=', null)
            ->select(['landlord_id', 'bank_account_number'])
            ->get();

        foreach ($configs as $config) {
            if ($config->bank_account_number === $notification->accountNumber) {
                return (int) $config->landlord_id;
            }
        }

        return null;
    }
}
