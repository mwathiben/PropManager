<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentConfiguration;

/**
 * Phase-40 GATEWAY-CONTRACT-1 shell. StripeService::withConfig +
 * isConfigured + ensureConfigured land in Phase 1a so the slot in
 * PaymentGatewayManager can resolve without crashing. The real
 * Stripe SDK calls (initializeTransaction, verifyTransaction,
 * refundTransaction, webhook signature verification) land in
 * Phase 1b — GATEWAY-STRIPE-1.
 */
class StripeService
{
    protected string $secretKey = '';

    protected string $publicKey = '';

    protected string $webhookSecret = '';

    public function __construct(?PaymentConfiguration $config = null)
    {
        if ($config !== null && $config->hasStripeConfig()) {
            $this->secretKey = (string) $config->stripe_secret_key;
            $this->publicKey = (string) $config->stripe_public_key;
            $this->webhookSecret = (string) ($config->stripe_webhook_secret ?? '');
        }
    }

    public function withConfig(PaymentConfiguration $config): self
    {
        if (! $config->hasStripeConfig()) {
            throw new \InvalidArgumentException(
                'StripeService requires a PaymentConfiguration with Stripe credentials. '
                .'Configure in Settings > Payment Methods.'
            );
        }

        $this->secretKey = (string) $config->stripe_secret_key;
        $this->publicKey = (string) $config->stripe_public_key;
        $this->webhookSecret = (string) ($config->stripe_webhook_secret ?? '');

        return $this;
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->publicKey !== '';
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey === '' ? null : $this->publicKey;
    }

    protected function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \InvalidArgumentException(
                'StripeService requires Stripe credentials. Call withConfig() first or construct with PaymentConfiguration.'
            );
        }
    }

    public static function generateReference(string $prefix = 'PAY'): string
    {
        return strtoupper($prefix.'_'.bin2hex(random_bytes(8)));
    }
}
