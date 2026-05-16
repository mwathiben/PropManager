<?php

namespace Tests\Unit\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\Gateways\MpesaGateway;
use App\Services\Gateways\PaystackGateway;
use App\Services\Gateways\StripeGateway;
use App\Services\MpesaService;
use App\Services\PaymentGatewayManager;
use App\Services\PaystackService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentGatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentGatewayManager $manager;

    protected PaymentConfiguration $config;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mpesa.environment' => 'sandbox',
            'mpesa.endpoints.sandbox' => 'https://sandbox.safaricom.co.ke',
        ]);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_xxxxxxxxxxxxx',
            'paystack_secret_key' => 'sk_test_xxxxxxxxxxxxx',
            'mpesa_consumer_key' => 'test_consumer_key',
            'mpesa_consumer_secret' => 'test_consumer_secret',
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey',
        ]);

        $this->manager = new PaymentGatewayManager(
            new PaystackService($this->config),
            new MpesaService($this->config),
            new StripeService(null),
        );
    }

    public function test_gets_paystack_gateway(): void
    {
        $gateway = $this->manager->gateway('paystack');

        $this->assertInstanceOf(PaystackGateway::class, $gateway);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('paystack', $gateway->getIdentifier());
    }

    public function test_gets_mpesa_gateway(): void
    {
        $gateway = $this->manager->gateway('mpesa');

        $this->assertInstanceOf(MpesaGateway::class, $gateway);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('mpesa', $gateway->getIdentifier());
    }

    public function test_gets_mpesa_gateway_with_hyphen(): void
    {
        $gateway = $this->manager->gateway('m-pesa');

        $this->assertInstanceOf(MpesaGateway::class, $gateway);
    }

    public function test_gateway_name_is_case_insensitive(): void
    {
        $paystack = $this->manager->gateway('PAYSTACK');
        $mpesa = $this->manager->gateway('MPESA');

        $this->assertEquals('paystack', $paystack->getIdentifier());
        $this->assertEquals('mpesa', $mpesa->getIdentifier());
    }

    public function test_throws_exception_for_unknown_gateway(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown payment gateway: nope');

        $this->manager->gateway('nope');
    }

    public function test_gets_stripe_gateway(): void
    {
        $gateway = $this->manager->gateway('stripe');

        $this->assertInstanceOf(StripeGateway::class, $gateway);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('stripe', $gateway->getIdentifier());
    }

    public function test_caches_gateway_instances(): void
    {
        $first = $this->manager->gateway('paystack');
        $second = $this->manager->gateway('paystack');

        $this->assertSame($first, $second);
    }

    public function test_returns_default_gateway(): void
    {
        config(['services.payment.default' => 'paystack']);

        $gateway = $this->manager->defaultGateway();

        $this->assertInstanceOf(PaystackGateway::class, $gateway);
    }

    public function test_returns_mpesa_as_default_when_configured(): void
    {
        config(['services.payment.default' => 'mpesa']);

        $gateway = $this->manager->defaultGateway();

        $this->assertInstanceOf(MpesaGateway::class, $gateway);
    }

    public function test_lists_supported_gateways(): void
    {
        $supported = $this->manager->supportedGateways();

        $this->assertContains('paystack', $supported);
        $this->assertContains('mpesa', $supported);
        $this->assertContains('stripe', $supported);
        $this->assertCount(3, $supported);
    }

    public function test_checks_if_gateway_is_supported(): void
    {
        $this->assertTrue($this->manager->supports('paystack'));
        $this->assertTrue($this->manager->supports('mpesa'));
        $this->assertTrue($this->manager->supports('stripe'));
        $this->assertFalse($this->manager->supports('square'));
    }

    public function test_checks_if_gateway_is_configured(): void
    {
        $this->assertTrue($this->manager->isConfigured('paystack'));
        $this->assertTrue($this->manager->isConfigured('mpesa'));
        // Stripe is registered but no credentials yet (Phase 1b lands creds).
        $this->assertFalse($this->manager->isConfigured('stripe'));
    }

    public function test_returns_unconfigured_for_empty_credentials(): void
    {
        $manager = new PaymentGatewayManager(
            new PaystackService(null),
            new MpesaService($this->config),
            new StripeService(null),
        );

        $this->assertFalse($manager->isConfigured('paystack'));
    }

    public function test_paystack_shortcut_method(): void
    {
        $gateway = $this->manager->paystack();

        $this->assertInstanceOf(PaystackGateway::class, $gateway);
    }

    public function test_mpesa_shortcut_method(): void
    {
        $gateway = $this->manager->mpesa();

        $this->assertInstanceOf(MpesaGateway::class, $gateway);
    }

    public function test_lists_available_configured_gateways(): void
    {
        $available = $this->manager->available();

        $this->assertArrayHasKey('paystack', $available);
        $this->assertArrayHasKey('mpesa', $available);
    }

    public function test_excludes_unconfigured_from_available(): void
    {
        $manager = new PaymentGatewayManager(
            new PaystackService(null),
            new MpesaService($this->config),
            new StripeService(null),
        );

        $available = $manager->available();

        $this->assertArrayNotHasKey('paystack', $available);
        $this->assertArrayHasKey('mpesa', $available);
    }

    public function test_gateway_returns_underlying_service(): void
    {
        $paystackGateway = $this->manager->paystack();
        $mpesaGateway = $this->manager->mpesa();

        $this->assertInstanceOf(PaystackService::class, $paystackGateway->getService());
        $this->assertInstanceOf(MpesaService::class, $mpesaGateway->getService());
    }

    public function test_gateway_generates_reference(): void
    {
        $gateway = $this->manager->paystack();
        $reference = $gateway->generateReference('TEST');

        $this->assertStringStartsWith('TEST-', $reference);
    }

    public function test_gateway_gets_public_key(): void
    {
        $gateway = $this->manager->paystack();
        $publicKey = $gateway->getPublicKey();

        $this->assertEquals('pk_test_xxxxxxxxxxxxx', $publicKey);
    }

    public function test_mpesa_gateway_returns_null_public_key(): void
    {
        $gateway = $this->manager->mpesa();
        $publicKey = $gateway->getPublicKey();

        $this->assertNull($publicKey);
    }
}
