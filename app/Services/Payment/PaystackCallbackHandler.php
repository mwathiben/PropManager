<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\WebhookDeadLetter;
use App\Services\BillingModelService;
use App\Services\IdempotencyService;
use App\Services\PaystackService;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\Log;

class PaystackCallbackHandler
{
    private const AMOUNT_TOLERANCE = 1.00;

    /** @var callable|null */
    private $overpaymentHandler = null;

    public function __construct(
        protected BillingModelService $billingService,
        protected ReceiptService $receiptService,
        protected IdempotencyService $idempotencyService,
        protected WebhookDeadLetterService $deadLetterService
    ) {}

    public function processCallback(
        string $reference,
        ?int $fallbackLandlordId = null,
        ?callable $overpaymentHandler = null
    ): PaystackHandlerResult {
        $this->overpaymentHandler = $overpaymentHandler;

        $landlordId = $this->resolveLandlordId($reference, $fallbackLandlordId);
        $paymentConfig = $this->loadPaystackConfig($landlordId);

        if (! $paymentConfig) {
            return PaystackHandlerResult::notConfigured();
        }

        $verificationResult = $this->verifyPaystackTransaction($paymentConfig, $reference);
        if (! $verificationResult) {
            return PaystackHandlerResult::verificationFailed();
        }

        return $this->handleVerifiedCallback($verificationResult);
    }

    public function processWebhook(
        string $payload,
        ?string $signature,
        ?callable $overpaymentHandler = null
    ): PaystackHandlerResult {
        $this->overpaymentHandler = $overpaymentHandler;

        if (! $signature) {
            Log::warning('Paystack webhook missing signature');

            return PaystackHandlerResult::unauthorized('Invalid signature');
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            Log::error('Paystack webhook payload is not valid JSON', [
                'payload_preview' => substr($payload, 0, 200),
            ]);

            return PaystackHandlerResult::badRequest('Invalid JSON payload');
        }
        $data = $decoded['data'] ?? [];
        $metadata = $data['metadata'] ?? [];
        $landlordId = $metadata['landlord_id'] ?? null;

        if (! $landlordId) {
            Log::warning('Paystack webhook missing landlord_id in metadata', [
                'reference' => $data['reference'] ?? 'unknown',
            ]);

            return PaystackHandlerResult::badRequest('Missing landlord context');
        }

        $paymentConfig = $this->loadPaystackConfig((int) $landlordId);

        if (! $paymentConfig) {
            Log::warning('Paystack webhook for unconfigured landlord', [
                'landlord_id' => $landlordId,
            ]);

            return PaystackHandlerResult::badRequest('Landlord not configured');
        }

        if (! (new PaystackService($paymentConfig))->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook signature verification failed', [
                'landlord_id' => $landlordId,
                'reference' => $data['reference'] ?? 'unknown',
            ]);

            return PaystackHandlerResult::unauthorized('Invalid signature');
        }

        $event = $decoded['event'] ?? null;

        Log::info('Paystack webhook received', ['event' => $event, 'reference' => $data['reference'] ?? null]);

        if ($event === 'charge.success') {
            return $this->processChargeSuccess($data);
        }

        return PaystackHandlerResult::ignored();
    }

    private function verifyPaystackTransaction(PaymentConfiguration $config, string $reference): ?array
    {
        $verification = (new PaystackService($config))->verifyTransaction($reference);

        if (! $verification || ! $verification['status']) {
            return null;
        }

        $data = $verification['data'];

        return $data['status'] === 'success' ? $data : null;
    }

    private function handleVerifiedCallback(array $data): PaystackHandlerResult
    {
        $metadata = $data['metadata'] ?? [];

        if (($metadata['type'] ?? null) === 'initial_payment' && isset($metadata['verification_id'])) {
            return PaystackHandlerResult::initialPayment($data, $metadata);
        }

        $invoiceId = $metadata['invoice_id'] ?? null;

        if (! $invoiceId) {
            return PaystackHandlerResult::error('Invoice not found in payment data');
        }

        $amountValidation = $this->validateAmount($data, (int) $invoiceId);
        if ($amountValidation !== null) {
            return $amountValidation;
        }

        return $this->delegateToProcessor($data['reference'], (int) $invoiceId, $data, 'payment');
    }

    private function processChargeSuccess(array $data): PaystackHandlerResult
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return PaystackHandlerResult::badRequest('No reference provided');
        }

        $metadata = $data['metadata'] ?? [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (! $invoiceId) {
            return PaystackHandlerResult::ignored();
        }

        $amountValidation = $this->validateAmount($data, (int) $invoiceId);
        if ($amountValidation !== null) {
            return $amountValidation;
        }

        return $this->delegateToProcessor($reference, (int) $invoiceId, $data, 'webhook');
    }

    private function delegateToProcessor(
        string $reference,
        int $invoiceId,
        array $data,
        string $source
    ): PaystackHandlerResult {
        $processor = PaymentCallbackProcessor::make(
            $this->billingService,
            $this->receiptService,
            $this->idempotencyService,
            $this->deadLetterService
        )
            ->forReference($reference)
            ->forInvoice($invoiceId)
            ->withPaymentData($data)
            ->fromSource($source);

        if ($this->overpaymentHandler) {
            $processor->onOverpayment($this->overpaymentHandler);
        }

        $result = $processor->process();

        if ($result->isAlreadyProcessed()) {
            return PaystackHandlerResult::alreadyProcessed();
        }

        if ($result->isInvoiceNotFound()) {
            return PaystackHandlerResult::error('Invoice not found');
        }

        if ($result->isError()) {
            return PaystackHandlerResult::error($result->errorMessage ?? 'Processing failed');
        }

        $processor->sendNotifications($result);

        return PaystackHandlerResult::success($result);
    }

    private function resolveLandlordId(string $reference, ?int $fallbackId): ?int
    {
        $pendingPayment = Payment::where('paystack_reference', $reference)->first();

        return $pendingPayment?->landlord_id ?? $fallbackId;
    }

    private function loadPaystackConfig(?int $landlordId): ?PaymentConfiguration
    {
        if (! $landlordId) {
            return null;
        }

        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        if (! $config || ! $config->hasPaystackConfig()) {
            return null;
        }

        return $config;
    }

    private function validateAmount(array $data, int $invoiceId): ?PaystackHandlerResult
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            return null;
        }

        $currency = Currency::tryFrom($data['currency'] ?? '') ?? Currency::default();
        $paystackAmount = $currency->fromMinorUnits($data['amount'] ?? 0);
        $expectedAmount = (float) $invoice->total_due;
        $difference = abs($paystackAmount - $expectedAmount);

        if ($difference > self::AMOUNT_TOLERANCE) {
            Log::warning('Paystack amount mismatch', [
                'expected' => $expectedAmount,
                'received' => $paystackAmount,
                'difference' => $difference,
                'currency' => $currency->value,
                'invoice_id' => $invoiceId,
                'reference' => $data['reference'] ?? 'unknown',
            ]);

            $this->deadLetterService->capture(
                WebhookDeadLetter::PROVIDER_PAYSTACK,
                'charge.success',
                $data,
                "Amount mismatch: expected {$expectedAmount}, received {$paystackAmount}",
                WebhookDeadLetter::ERROR_SCHEMA,
                $invoice->landlord_id
            );

            return PaystackHandlerResult::amountMismatch($expectedAmount, $paystackAmount);
        }

        return null;
    }
}
