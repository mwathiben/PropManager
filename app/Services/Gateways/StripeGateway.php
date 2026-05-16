<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Services\StripeService;
use App\ValueObjects\Payment\Money;
use App\ValueObjects\Payment\PaymentRequest;
use App\ValueObjects\Payment\PaymentResult;
use Illuminate\Http\Request;

/**
 * Phase-40 GATEWAY-CONTRACT-1 shell. Real PaymentIntent /
 * webhook signature / refund logic lands in Phase 1b —
 * GATEWAY-STRIPE-1. Until then, every operational method returns
 * a 'gateway not yet implemented' failure result so callers see a
 * deterministic error instead of a 500.
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
        return PaymentResult::failed(
            error: 'Stripe gateway not yet implemented — Phase 40 [GATEWAY-STRIPE-1] in progress.',
            reference: $request->reference,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        return PaymentResult::failed(
            error: 'Stripe gateway not yet implemented — Phase 40 [GATEWAY-STRIPE-1] in progress.',
            reference: $reference,
        );
    }

    public function refundPayment(string $reference, ?Money $amount = null): PaymentResult
    {
        return PaymentResult::failed(
            error: 'Stripe gateway not yet implemented — Phase 40 [GATEWAY-STRIPE-1] in progress.',
            reference: $reference,
        );
    }

    public function validateWebhook(Request $request): bool
    {
        return false;
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
