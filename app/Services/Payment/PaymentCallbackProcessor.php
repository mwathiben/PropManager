<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Events\PaymentReceived as PaymentReceivedEvent;
use App\Exceptions\Payment\CurrencyMismatchException;
use App\Mail\PaymentReceived;
use App\Models\Invoice;
use App\Models\LandlordPayoutAccount;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use App\Services\BillingModelService;
use App\Services\IdempotencyService;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Processes Paystack payment callbacks and webhooks.
 *
 * Consolidates the shared logic between handleCallback (browser redirect)
 * and processSuccessfulCharge (server-to-server webhook).
 *
 * Uses two-layer idempotency:
 * 1. Application layer: IdempotencyService.acquire() - early detection
 * 2. Database layer: UNIQUE constraint on paystack_reference - safety net
 */
class PaymentCallbackProcessor
{
    private string $reference;

    private int $invoiceId;

    private array $paymentData;

    private string $source;

    /** @var callable|null */
    private $overpaymentHandler = null;

    private ?string $idempotencyKey = null;

    public function __construct(
        private BillingModelService $billingService,
        private ReceiptService $receiptService,
        private IdempotencyService $idempotencyService,
        private WebhookDeadLetterService $deadLetterService
    ) {}

    /**
     * Start building a payment processing request.
     */
    public static function make(
        BillingModelService $billingService,
        ReceiptService $receiptService,
        IdempotencyService $idempotencyService,
        WebhookDeadLetterService $deadLetterService
    ): self {
        return new self($billingService, $receiptService, $idempotencyService, $deadLetterService);
    }

    public function forReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function forInvoice(int $invoiceId): self
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }

    public function withPaymentData(array $data): self
    {
        $this->paymentData = $data;

        return $this;
    }

    public function fromSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function onOverpayment(callable $handler): self
    {
        $this->overpaymentHandler = $handler;

        return $this;
    }

    /**
     * Process the payment.
     *
     * Uses two-layer idempotency:
     * 1. IdempotencyService.acquire() FIRST - early detection before any processing
     * 2. UNIQUE constraint on paystack_reference - database safety net
     */
    public function process(): PaymentProcessResult
    {
        $this->idempotencyKey = "paystack:{$this->reference}";

        $idempotencyResult = $this->idempotencyService->acquire($this->idempotencyKey);

        if (! $idempotencyResult['acquired']) {
            Log::info('Paystack payment already handled (idempotency)', [
                'key' => $this->idempotencyKey,
                'cached' => $idempotencyResult['response'] !== null,
            ]);

            $existingPayment = Payment::where('paystack_reference', $this->reference)->first();

            return PaymentProcessResult::alreadyProcessed($existingPayment);
        }

        try {
            $result = DB::transaction(function () {
                $pendingOverpayments = [];

                $existingPayment = Payment::where('paystack_reference', $this->reference)
                    ->lockForUpdate()
                    ->first();

                if ($existingPayment) {
                    return PaymentProcessResult::alreadyProcessed($existingPayment);
                }

                $invoice = Invoice::where('id', $this->invoiceId)->lockForUpdate()->first();

                if (! $invoice) {
                    return PaymentProcessResult::invoiceNotFound();
                }

                $payment = $this->createPaymentRecord($invoice);
                $this->recordPlatformFee($payment, $invoice);
                $this->receiptService->createReceipt($payment, $invoice);

                $overpayment = $this->updateInvoiceAndHandleOverpayment(
                    $invoice,
                    $payment,
                    $pendingOverpayments
                );

                return PaymentProcessResult::success(
                    payment: $payment,
                    invoice: $invoice,
                    overpayment: $overpayment,
                    pendingOverpayments: $pendingOverpayments
                );
            });

            $this->idempotencyService->release($this->idempotencyKey, [
                'status' => $result->isSuccess() ? 'success' : ($result->isAlreadyProcessed() ? 'duplicate' : 'error'),
                'payment_id' => $result->payment?->id,
            ]);

            return $result;
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                $this->idempotencyService->release($this->idempotencyKey, ['status' => 'duplicate']);
                Log::info('Paystack duplicate payment ignored (idempotent)', [
                    'paystack_reference' => $this->reference,
                ]);

                $existingPayment = Payment::where('paystack_reference', $this->reference)->first();

                return PaymentProcessResult::alreadyProcessed($existingPayment);
            }

            $this->idempotencyService->fail($this->idempotencyKey, $e->getMessage());
            Log::error('Payment processing database error', [
                'reference' => $this->reference,
                'error' => $e->getMessage(),
            ]);

            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_PAYSTACK,
                'charge.success',
                $this->paymentData,
                $e->getMessage(),
                WebhookDeadLetter::ERROR_TRANSIENT,
                $this->resolvePaymentLandlordId()
            );

            return PaymentProcessResult::error($e->getMessage());
        } catch (\Exception $e) {
            $this->idempotencyService->fail($this->idempotencyKey, $e->getMessage());
            Log::error('Payment processing failed', [
                'reference' => $this->reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_PAYSTACK,
                'charge.success',
                $this->paymentData,
                $e->getMessage(),
                WebhookDeadLetter::ERROR_PERMANENT,
                $this->resolvePaymentLandlordId()
            );

            return PaymentProcessResult::error($e->getMessage());
        }
    }

    private function createPaymentRecord(Invoice $invoice): Payment
    {
        $currency = $this->resolvePaymentCurrency($invoice);
        $amount = $currency->fromMinorUnits($this->paymentData['amount']);
        $metadata = $this->paymentData['metadata'] ?? [];
        $isSplitPayment = $metadata['is_split_payment'] ?? false;
        $payoutAccountId = $metadata['payout_account_id'] ?? null;

        $splitCode = $this->extractSplitCode();

        return $invoice->payments()->create([
            'landlord_id' => $invoice->landlord_id,
            'lease_id' => $invoice->lease_id,
            'payout_account_id' => $payoutAccountId,
            'amount' => $amount,
            'currency' => $currency->value,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => $this->paymentData['reference'],
            'paystack_reference' => $this->reference,
            'paystack_split_code' => $splitCode,
            'is_split_payment' => $isSplitPayment,
            'notes' => "Paystack {$this->source} - ".($this->paymentData['channel'] ?? 'online'),
        ]);
    }

    private function extractSplitCode(): ?string
    {
        $subaccount = $this->paymentData['subaccount'] ?? null;

        if (is_array($subaccount)) {
            return $subaccount['subaccount_code'] ?? null;
        }

        return $subaccount;
    }

    private function recordPlatformFee(Payment $payment, Invoice $invoice): void
    {
        $landlord = User::find($invoice->landlord_id);

        if (! $landlord) {
            return;
        }

        $currency = $this->resolvePaymentCurrency($invoice);
        $amount = $currency->fromMinorUnits($this->paymentData['amount']);
        $metadata = $this->paymentData['metadata'] ?? [];
        $isSplitPayment = $metadata['is_split_payment'] ?? false;
        $payoutAccountId = $metadata['payout_account_id'] ?? null;

        $feeResult = $this->billingService->calculatePlatformFee($amount, $landlord);

        $payoutAccount = $payoutAccountId
            ? LandlordPayoutAccount::find($payoutAccountId)
            : null;

        $splitDetails = null;
        if ($isSplitPayment) {
            $splitDetails = [
                'subaccount' => $this->paymentData['subaccount'] ?? null,
                'fees_split' => $this->paymentData['fees_split'] ?? null,
            ];

            if (isset($this->paymentData['authorization'])) {
                $splitDetails['authorization'] = $this->paymentData['authorization'];
            }
        }

        $this->billingService->recordPlatformFee(
            payment: $payment,
            feeResult: $feeResult,
            payoutAccount: $payoutAccount,
            splitReference: $isSplitPayment ? $this->reference : null,
            splitDetails: $splitDetails
        );
    }

    private function updateInvoiceAndHandleOverpayment(
        Invoice $invoice,
        Payment $payment,
        array &$pendingOverpayments
    ): float {
        $paymentCurrency = $this->resolvePaymentCurrency($invoice);
        $invoiceCurrency = $invoice->currency ?? Currency::default();
        $rawAmount = $this->paymentData['amount'] ?? 0;
        $amount = $paymentCurrency->fromMinorUnits($rawAmount);

        if ($paymentCurrency !== $invoiceCurrency) {
            throw new CurrencyMismatchException(
                $paymentCurrency->value,
                $invoiceCurrency->value,
                $invoice->id
            );
        }

        $remainingBalance = $invoice->total_due - $invoice->amount_paid;
        $appliedAmount = min($amount, $remainingBalance);
        $overpayment = max(0, $amount - $remainingBalance);

        $newAmountPaid = $invoice->amount_paid + $appliedAmount;
        $newStatus = $newAmountPaid >= $invoice->total_due ? InvoiceStatus::Paid : InvoiceStatus::Partial;

        $invoice->update([
            'amount_paid' => $newAmountPaid,
            'status' => $newStatus,
        ]);

        $lease = $invoice->lease;
        if ($overpayment > 0 && $lease) {
            $lease->creditToWallet(
                $overpayment,
                "Overpayment from Paystack {$this->source} #{$payment->id}",
                $payment->id
            );
            $lease->refresh();

            $pendingOverpayments[] = [
                'payment_id' => $payment->id,
                'lease_id' => $lease->id,
                'overpayment' => $overpayment,
            ];
        }

        return $overpayment;
    }

    private function resolvePaymentCurrency(Invoice $invoice): Currency
    {
        return Currency::tryFrom($this->paymentData['currency'] ?? '')
            ?? $invoice->currency
            ?? Currency::default();
    }

    private function resolvePaymentLandlordId(): ?int
    {
        return Invoice::find($this->invoiceId)?->landlord_id;
    }

    /**
     * Send notifications after successful processing.
     */
    public function sendNotifications(PaymentProcessResult $result): void
    {
        if (! $result->isSuccess()) {
            return;
        }

        if ($this->overpaymentHandler && ! empty($result->pendingOverpayments)) {
            call_user_func($this->overpaymentHandler, $result->pendingOverpayments);
        }

        $invoice = $result->invoice;
        $invoice->load(['lease.tenant', 'lease.unit.building']);
        $tenant = $invoice->lease?->tenant;

        if ($tenant) {
            // CONC-3: queue, not send. Sync SMTP previously blocked the
            // gateway-callback request thread — bad for webhook latency
            // budgets and unnecessary because the mailable doesn't need
            // to be delivered before responding to the gateway.
            Mail::to($tenant->email)->queue(new PaymentReceived($result->payment, $invoice));
            PaymentReceivedEvent::dispatch($result->payment, $invoice);
        }
    }
}
