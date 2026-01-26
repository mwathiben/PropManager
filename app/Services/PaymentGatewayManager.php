<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Gateways\MpesaGateway;
use App\Services\Gateways\PaystackGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function __construct(
        protected PaystackService $paystackService,
        protected MpesaService $mpesaService,
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
        return ['paystack', 'mpesa'];
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
}
