<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\Currency;
use App\Services\Gateways\MpesaGateway;
use App\Services\Gateways\PaystackGateway;
use App\Services\Gateways\StripeGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function __construct(
        protected PaystackService $paystackService,
        protected MpesaService $mpesaService,
        protected StripeService $stripeService,
    ) {}

    /**
     * Get a specific gateway by name.
     *
     * @throws InvalidArgumentException
     */
    public function gateway(string $name): PaymentGatewayInterface
    {
        $name = strtolower($name);

        if (isset($this->gateways[$name])) {
            return $this->gateways[$name];
        }

        $gateway = match ($name) {
            'paystack' => new PaystackGateway($this->paystackService),
            'mpesa', 'm-pesa' => new MpesaGateway($this->mpesaService),
            'stripe' => new StripeGateway($this->stripeService),
            default => throw new InvalidArgumentException("Unknown payment gateway: {$name}"),
        };

        $this->gateways[$name] = $gateway;

        return $gateway;
    }

    /**
     * Get the default gateway based on configuration.
     */
    public function defaultGateway(): PaymentGatewayInterface
    {
        $default = config('services.payment.default', 'paystack');

        return $this->gateway($default);
    }

    /**
     * Get all available (configured) gateways.
     *
     * @return array<string, PaymentGatewayInterface>
     */
    public function available(): array
    {
        $available = [];

        foreach ($this->supportedGateways() as $name) {
            $gateway = $this->gateway($name);
            if ($gateway->isConfigured()) {
                $available[$name] = $gateway;
            }
        }

        return $available;
    }

    /**
     * Get list of supported gateway names.
     *
     * @return string[]
     */
    public function supportedGateways(): array
    {
        return ['paystack', 'mpesa', 'stripe'];
    }

    /**
     * Check if a gateway is supported.
     */
    public function supports(string $name): bool
    {
        return in_array(strtolower($name), $this->supportedGateways(), true);
    }

    /**
     * Check if a gateway is configured and ready to use.
     */
    public function isConfigured(string $name): bool
    {
        if (! $this->supports($name)) {
            return false;
        }

        return $this->gateway($name)->isConfigured();
    }

    /**
     * Get the Paystack gateway directly.
     */
    public function paystack(): PaystackGateway
    {
        /** @var PaystackGateway */
        return $this->gateway('paystack');
    }

    /**
     * Get the M-Pesa gateway directly.
     */
    public function mpesa(): MpesaGateway
    {
        /** @var MpesaGateway */
        return $this->gateway('mpesa');
    }

    /**
     * Get the Stripe gateway directly.
     */
    public function stripe(): StripeGateway
    {
        /** @var StripeGateway */
        return $this->gateway('stripe');
    }

    /**
     * Phase-40 GATEWAY-CURRENCY-3: route to the correct gateway based
     * on currency. KES → Paystack (Kenyan domestic), USD/EUR/GBP →
     * Stripe (international cards). Centralised so callers don't
     * hand-code the if/else at N sites.
     *
     * Override per-user via $user->payment_gateway_preference (Phase 1f).
     */
    public function routeFor(Currency|string $currency): PaymentGatewayInterface
    {
        $code = $currency instanceof Currency ? $currency->value : strtoupper($currency);

        $name = $code === Currency::KES->value ? 'paystack' : 'stripe';

        return $this->gateway($name);
    }

    /**
     * Phase-40 GATEWAY-PREF-1: per-user override of currency-based
     * routing. payment_gateway_preference = 'auto' falls back to
     * routeFor(currency); explicit paystack/stripe forces that gateway.
     */
    public function routeForUser(\App\Models\User $user, Currency|string $currency): PaymentGatewayInterface
    {
        $pref = $user->payment_gateway_preference ?? 'auto';
        if ($pref === 'auto') {
            return $this->routeFor($currency);
        }

        return $this->gateway($pref);
    }
}
