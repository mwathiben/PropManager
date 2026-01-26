<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\Payment\Money;
use App\ValueObjects\Payment\PaymentRequest;
use App\ValueObjects\Payment\PaymentResult;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Get the gateway identifier (e.g., 'paystack', 'mpesa').
     */
    public function getIdentifier(): string;

    /**
     * Check if the gateway has valid credentials configured.
     */
    public function isConfigured(): bool;

    /**
     * Initialize a payment transaction.
     */
    public function initializePayment(PaymentRequest $request): PaymentResult;

    /**
     * Verify a payment by its reference.
     */
    public function verifyPayment(string $reference): PaymentResult;

    /**
     * Initiate a refund for a payment.
     */
    public function refundPayment(string $reference, ?Money $amount = null): PaymentResult;

    /**
     * Validate an incoming webhook request.
     */
    public function validateWebhook(Request $request): bool;

    /**
     * Get the public key for frontend integrations (if applicable).
     */
    public function getPublicKey(): ?string;

    /**
     * Generate a unique payment reference.
     */
    public function generateReference(string $prefix = 'PAY'): string;
}
