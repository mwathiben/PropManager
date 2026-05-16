<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\Integration\PaymentGatewayUnreachableException;
use App\Services\StripeService;
use App\ValueObjects\Payment\Money;
use App\ValueObjects\Payment\PaymentRequest;
use App\ValueObjects\Payment\PaymentResult;
use Illuminate\Http\Request;

/**
 * Phase-40 GATEWAY-STRIPE-1: PaymentGatewayInterface adapter
 * delegating to StripeService (per-landlord credentials).
 */
class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected StripeService $service,
    ) {}

    public function getIdentifier(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return $this->service->isConfigured();
    }

    public function initializePayment(PaymentRequest $request): PaymentResult
    {
        try {
            $response = $this->service->createPaymentIntent([
                'amount' => $request->amount->toSmallestUnit(),
                'currency' => $request->amount->currency,
                'reference' => $request->reference,
                'metadata' => $request->metadata ?? [],
                'receipt_email' => $request->email,
            ]);
        } catch (PaymentGatewayUnreachableException $e) {
            return PaymentResult::failed(
                error: $e->getMessage(),
                errorCode: $e->getErrorCode(),
                reference: $request->reference,
            );
        }

        if ($response === null || ! ($response['status'] ?? false)) {
            return PaymentResult::failed(
                error: $response['message'] ?? 'Failed to initialize Stripe PaymentIntent',
                reference: $request->reference,
                rawResponse: $response,
            );
        }

        $data = $response['data'] ?? [];

        return PaymentResult::initialized(
            reference: $data['reference'] ?? $request->reference,
            authorizationUrl: '',
            accessCode: $data['client_secret'] ?? null,
            rawResponse: $response,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        $response = $this->service->retrievePaymentIntent($reference);

        if ($response === null || ! ($response['status'] ?? false)) {
            return PaymentResult::failed(
                error: $response['message'] ?? 'Failed to verify Stripe PaymentIntent',
                reference: $reference,
                rawResponse: $response,
            );
        }

        $data = $response['data'] ?? [];

        return PaymentResult::verified(
            reference: $data['reference'] ?? $reference,
            status: $data['status'] ?? 'failed',
            amount: Money::fromSmallestUnit((int) ($data['amount'] ?? 0), $data['currency'] ?? 'USD'),
            transactionId: $data['reference'] ?? null,
            rawResponse: $response,
        );
    }

    public function refundPayment(string $reference, ?Money $amount = null): PaymentResult
    {
        $response = $this->service->refund($reference, $amount?->toSmallestUnit());

        if ($response === null || ! ($response['status'] ?? false)) {
            return PaymentResult::failed(
                error: $response['message'] ?? 'Failed to process Stripe refund',
                reference: $reference,
                rawResponse: $response,
            );
        }

        return PaymentResult::refunded(
            reference: $response['reference'] ?? $reference,
            amount: Money::fromSmallestUnit((int) ($response['amount'] ?? 0), $response['currency'] ?? 'USD'),
            transactionId: $response['reference'] ?? null,
            rawResponse: $response,
        );
    }

    public function validateWebhook(Request $request): bool
    {
        $sigHeader = $request->header('Stripe-Signature', '');

        return $this->service->verifyWebhookSignature($request->getContent(), (string) $sigHeader);
    }

    public function getPublicKey(): ?string
    {
        return $this->service->getPublicKey();
    }

    public function generateReference(string $prefix = 'PAY'): string
    {
        return StripeService::generateReference($prefix);
    }

    public function getService(): StripeService
    {
        return $this->service;
    }
}
