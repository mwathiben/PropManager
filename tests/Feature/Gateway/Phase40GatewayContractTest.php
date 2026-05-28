<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Enums\PaymentMethod;
use App\Services\Gateways\StripeGateway;
use App\Services\PaymentGatewayManager;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-40 GATEWAY-CONTRACT-1/2: PaymentGatewayManager registers
 * 'stripe' + PaymentMethod enum has the Stripe case fixing the
 * pre-Phase-40 schema/app-enum drift.
 */
class Phase40GatewayContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_method_enum_includes_stripe_case(): void
    {
        $values = PaymentMethod::values();
        $this->assertContains('stripe', $values, 'PaymentMethod::Stripe case missing — schema/app-enum drift not fixed.');
        $this->assertSame('Stripe (USD/EUR/GBP)', PaymentMethod::Stripe->label());
    }

    public function test_payment_method_stripe_round_trips_through_from(): void
    {
        $method = PaymentMethod::from('stripe');
        $this->assertSame(PaymentMethod::Stripe, $method);
    }

    public function test_payment_gateway_manager_resolves_stripe_gateway(): void
    {
        $gateway = app(PaymentGatewayManager::class)->gateway('stripe');
        $this->assertInstanceOf(StripeGateway::class, $gateway);
        $this->assertSame('stripe', $gateway->getIdentifier());
    }

    public function test_stripe_gateway_shell_returns_unconfigured_when_no_creds(): void
    {
        $manager = app(PaymentGatewayManager::class);
        $this->assertFalse($manager->isConfigured('stripe'));
    }

    public function test_stripe_service_throws_when_with_config_called_without_creds(): void
    {
        $config = new \App\Models\PaymentConfiguration;
        $this->expectException(\InvalidArgumentException::class);
        (new StripeService(null))->withConfig($config);
    }

    public function test_supported_gateways_lists_stripe(): void
    {
        $supported = app(PaymentGatewayManager::class)->supportedGateways();
        $this->assertContains('stripe', $supported);
    }
}
