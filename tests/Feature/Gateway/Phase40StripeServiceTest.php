<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\StripeService;
use App\Services\StripeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-40 GATEWAY-STRIPE-1/2/3: StripeService + StripeSubscriptionService
 * + payment_configurations migration.
 */
class Phase40StripeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_configurations_table_has_stripe_columns(): void
    {
        $cols = \Schema::getColumnListing('payment_configurations');
        $this->assertContains('stripe_enabled', $cols);
        $this->assertContains('stripe_public_key', $cols);
        $this->assertContains('stripe_secret_key', $cols);
        $this->assertContains('stripe_webhook_secret', $cols);
    }

    public function test_payment_configuration_has_stripe_config_when_credentials_present(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_enabled' => true,
            'stripe_public_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_123',
        ]);

        $this->assertTrue($config->hasStripeConfig());
    }

    public function test_payment_configuration_has_stripe_config_returns_false_when_disabled(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_enabled' => false,
            'stripe_public_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_123',
        ]);

        $this->assertFalse($config->hasStripeConfig());
    }

    public function test_stripe_service_is_configured_when_with_config_called(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_enabled' => true,
            'stripe_public_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_123',
        ]);

        $service = (new StripeService(null))->withConfig($config);
        $this->assertTrue($service->isConfigured());
        $this->assertSame('pk_test_123', $service->getPublicKey());
    }

    public function test_stripe_service_webhook_verification_rejects_when_no_secret(): void
    {
        $service = new StripeService(null);
        $this->assertFalse($service->verifyWebhookSignature('{}', 'sig'));
    }

    public function test_stripe_subscription_service_unconfigured_by_default(): void
    {
        $service = new StripeSubscriptionService;
        $this->assertFalse($service->isConfigured());
    }

    public function test_stripe_subscription_service_create_or_update_plan_noops_when_unconfigured(): void
    {
        $service = new StripeSubscriptionService;
        $plan = \App\Models\SubscriptionPlan::factory()->create();
        $this->assertNull($service->createOrUpdatePlan($plan));
    }

    public function test_stripe_php_sdk_is_installed(): void
    {
        $this->assertTrue(class_exists(\Stripe\StripeClient::class));
        $this->assertTrue(class_exists(\Stripe\Webhook::class));
        $this->assertTrue(class_exists(\Stripe\PaymentIntent::class));
    }
}
