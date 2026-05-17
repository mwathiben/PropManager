<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Enums\StripeConnectAccountType;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\StripeConnectStandardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-42 CONNECT-STANDARD-1/2/3: StripeConnectStandardService +
 * stripe_connect_account_type enum + StripeService createPaymentIntent
 * branches on type for destination vs direct charges.
 */
class Phase42ConnectStandardTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_configurations_has_stripe_connect_account_type_column(): void
    {
        $this->assertContains('stripe_connect_account_type', Schema::getColumnListing('payment_configurations'));
    }

    public function test_stripe_connect_account_type_defaults_to_express(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create();

        $config->refresh();
        $this->assertSame(StripeConnectAccountType::Express, $config->stripe_connect_account_type);
    }

    public function test_stripe_connect_account_type_enum_round_trips_for_standard(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_connect_account_type' => StripeConnectAccountType::Standard->value,
        ]);

        $reloaded = PaymentConfiguration::find($config->id);
        $this->assertSame(StripeConnectAccountType::Standard, $reloaded->stripe_connect_account_type);
    }

    public function test_standard_service_unconfigured_by_default(): void
    {
        $service = new StripeConnectStandardService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_create_standard_account_noops_when_unconfigured(): void
    {
        $service = new StripeConnectStandardService();
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->assertNull($service->createStandardAccount($landlord));
    }

    public function test_standard_onboarding_link_noops_when_unconfigured(): void
    {
        $service = new StripeConnectStandardService();
        $this->assertNull($service->onboardingLink('acct_test_123', 'https://example.test/return', 'https://example.test/refresh'));
    }

    public function test_standard_sync_account_status_noops_when_unconfigured(): void
    {
        $service = new StripeConnectStandardService();
        $this->assertNull($service->syncAccountStatus('acct_test_123'));
    }

    public function test_stripe_connect_account_type_values_helper(): void
    {
        $this->assertSame(['express', 'standard'], StripeConnectAccountType::values());
    }

    public function test_payment_configurations_supports_setting_account_type_via_factory_state(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_connect_account_id' => 'acct_test_standard_'.uniqid(),
            'stripe_connect_account_type' => StripeConnectAccountType::Standard->value,
        ]);

        $this->assertSame(StripeConnectAccountType::Standard, $config->stripe_connect_account_type);
        $this->assertNotEmpty($config->stripe_connect_account_id);
    }
}
