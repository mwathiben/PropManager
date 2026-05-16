<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Enums\Currency;
use App\Services\Gateways\PaystackGateway;
use App\Services\Gateways\StripeGateway;
use App\Services\PaymentGatewayManager;
use App\ValueObjects\Payment\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-40 GATEWAY-CURRENCY-1/2/3: stripe_plan_code migration on
 * subscription_plans + Money::toStripeAmount + PaymentGatewayManager::routeFor.
 */
class Phase40CurrencyRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_plans_has_stripe_plan_code_column(): void
    {
        $cols = \Schema::getColumnListing('subscription_plans');
        $this->assertContains('stripe_plan_code', $cols);
    }

    public function test_money_to_stripe_amount_returns_minor_units(): void
    {
        $money = Money::fromSmallestUnit(15000, 'USD');
        $this->assertSame(15000, $money->toStripeAmount());
        $this->assertSame(15000, $money->toPaystackAmount(), 'Stripe + Paystack helpers must be byte-equal — both speak minor units.');
    }

    public function test_route_for_kes_returns_paystack(): void
    {
        $gateway = app(PaymentGatewayManager::class)->routeFor(Currency::KES);
        $this->assertInstanceOf(PaystackGateway::class, $gateway);
    }

    public function test_route_for_usd_returns_stripe(): void
    {
        $gateway = app(PaymentGatewayManager::class)->routeFor(Currency::USD);
        $this->assertInstanceOf(StripeGateway::class, $gateway);
    }

    public function test_route_for_eur_returns_stripe(): void
    {
        $gateway = app(PaymentGatewayManager::class)->routeFor(Currency::EUR);
        $this->assertInstanceOf(StripeGateway::class, $gateway);
    }

    public function test_route_for_accepts_string_alias_uppercased(): void
    {
        $gateway = app(PaymentGatewayManager::class)->routeFor('usd');
        $this->assertInstanceOf(StripeGateway::class, $gateway);
    }
}
