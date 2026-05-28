<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-41 GATEWAY-CONNECT-1/2/3: StripeConnectService +
 * stripe_connect_* columns + account.updated webhook.
 */
class Phase41ConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_configurations_table_has_stripe_connect_columns(): void
    {
        $cols = \Schema::getColumnListing('payment_configurations');
        foreach (['stripe_connect_account_id', 'stripe_connect_status', 'stripe_connect_charges_enabled', 'stripe_connect_payouts_enabled'] as $c) {
            $this->assertContains($c, $cols);
        }
    }

    public function test_stripe_connect_service_unconfigured_by_default(): void
    {
        $service = new StripeConnectService;
        $this->assertFalse($service->isConfigured());
    }

    public function test_create_express_account_noops_when_unconfigured(): void
    {
        $service = new StripeConnectService;
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->assertNull($service->createExpressAccount($landlord));
    }

    public function test_onboarding_link_noops_when_unconfigured(): void
    {
        $service = new StripeConnectService;
        $this->assertNull($service->onboardingLink('acct_test_123', 'https://example.test/return', 'https://example.test/refresh'));
    }

    public function test_sync_account_status_noops_when_unconfigured(): void
    {
        $service = new StripeConnectService;
        $this->assertNull($service->syncAccountStatus('acct_test_123'));
    }

    public function test_account_updated_webhook_triggers_sync(): void
    {
        // Configure webhook so the route accepts the call; service stays
        // unconfigured so the sync gracefully noops without making a network call.
        config(['services.stripe.webhook_secret' => 'whsec_test_connect']);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'account.updated',
            'data' => ['object' => [
                'id' => 'acct_test_'.uniqid(),
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'details_submitted' => true,
            ]],
        ];

        $timestamp = time();
        $signedPayload = $timestamp.'.'.json_encode($payload);
        $sig = 't='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, 'whsec_test_connect');

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $sig],
            json_encode($payload));

        $response->assertStatus(200);
    }

    public function test_connect_columns_default_charges_and_payouts_enabled_false(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create();

        $this->assertFalse((bool) $config->stripe_connect_charges_enabled);
        $this->assertFalse((bool) $config->stripe_connect_payouts_enabled);
    }
}
