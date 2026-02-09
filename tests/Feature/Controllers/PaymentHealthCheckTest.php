<?php

namespace Tests\Feature\Controllers;

use App\Models\PaymentConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentHealthCheckTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_payment_health_endpoint_returns_200(): void
    {
        $response = $this->getJson('/api/health/payments');

        $response->assertOk();
    }

    public function test_payment_health_returns_expected_json_structure(): void
    {
        $response = $this->getJson('/api/health/payments');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'gateways' => [
                    'paystack' => ['status', 'configured_count'],
                    'mpesa' => ['status', 'configured_count'],
                    'intasend' => ['status', 'configured_count'],
                ],
                'checked_at',
            ]);
    }

    public function test_payment_health_does_not_require_authentication(): void
    {
        $response = $this->getJson('/api/health/payments');

        $response->assertOk();
    }

    public function test_gateways_show_not_configured_when_no_configs_exist(): void
    {
        $response = $this->getJson('/api/health/payments');

        $response->assertOk()
            ->assertJsonPath('gateways.paystack.status', 'not_configured')
            ->assertJsonPath('gateways.paystack.configured_count', 0)
            ->assertJsonPath('gateways.mpesa.status', 'not_configured')
            ->assertJsonPath('gateways.mpesa.configured_count', 0)
            ->assertJsonPath('gateways.intasend.status', 'not_configured')
            ->assertJsonPath('gateways.intasend.configured_count', 0);
    }

    public function test_paystack_shows_configured_when_landlord_has_config(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_healthcheck123',
            'paystack_secret_key' => 'sk_test_healthcheck456',
        ]);

        $response = $this->getJson('/api/health/payments');

        $response->assertOk()
            ->assertJsonPath('gateways.paystack.status', 'configured')
            ->assertJsonPath('gateways.paystack.configured_count', 1);
    }

    public function test_mpesa_shows_configured_when_landlord_has_api_config(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'mpesa_consumer_key' => 'test_consumer_key',
            'mpesa_consumer_secret' => 'test_consumer_secret',
            'mpesa_environment' => 'sandbox',
        ]);

        $response = $this->getJson('/api/health/payments');

        $response->assertOk()
            ->assertJsonPath('gateways.mpesa.status', 'configured')
            ->assertJsonPath('gateways.mpesa.configured_count', 1);
    }

    public function test_intasend_shows_configured_when_landlord_has_config(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_test_healthcheck',
            'intasend_secret_key' => 'ISSecretKey_test_healthcheck',
            'intasend_environment' => 'sandbox',
        ]);

        $response = $this->getJson('/api/health/payments');

        $response->assertOk()
            ->assertJsonPath('gateways.intasend.status', 'configured')
            ->assertJsonPath('gateways.intasend.configured_count', 1);
    }

    public function test_configured_count_reflects_multiple_landlords(): void
    {
        $setup1 = $this->createLandlordWithFullSetup();
        $setup2 = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup1['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_one',
            'paystack_secret_key' => 'sk_test_one',
        ]);

        PaymentConfiguration::create([
            'landlord_id' => $setup2['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_two',
            'paystack_secret_key' => 'sk_test_two',
        ]);

        $response = $this->getJson('/api/health/payments');

        $response->assertOk()
            ->assertJsonPath('gateways.paystack.configured_count', 2);
    }

    public function test_ping_checks_gateway_api_reachability(): void
    {
        Http::fake([
            'api.paystack.co*' => Http::response('', 200),
        ]);

        $setup = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_ping',
            'paystack_secret_key' => 'sk_test_ping',
        ]);

        $response = $this->getJson('/api/health/payments?ping=true');

        $response->assertOk()
            ->assertJsonPath('gateways.paystack.status', 'ok')
            ->assertJsonPath('gateways.paystack.configured_count', 1);

        $this->assertArrayHasKey(
            'response_time_ms',
            $response->json('gateways.paystack')
        );
    }

    public function test_ping_returns_degraded_when_api_unreachable(): void
    {
        Http::fake([
            'api.paystack.co*' => Http::response('Server Error', 500),
        ]);

        $setup = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_degraded',
            'paystack_secret_key' => 'sk_test_degraded',
        ]);

        $response = $this->getJson('/api/health/payments?ping=true');

        $response->assertOk()
            ->assertJsonPath('gateways.paystack.status', 'degraded');
    }

    public function test_ping_results_are_cached_for_five_minutes(): void
    {
        Http::fake([
            'api.paystack.co*' => Http::response('', 200),
        ]);

        $setup = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_cache',
            'paystack_secret_key' => 'sk_test_cache',
        ]);

        $this->getJson('/api/health/payments?ping=true');
        $this->getJson('/api/health/payments?ping=true');

        Http::assertSentCount(1);
    }

    public function test_response_does_not_expose_api_keys(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_secret_value_xyz',
            'paystack_secret_key' => 'sk_test_secret_value_abc',
            'mpesa_consumer_key' => 'mpesa_key_should_not_appear',
            'mpesa_consumer_secret' => 'mpesa_secret_should_not_appear',
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_should_not_appear',
            'intasend_secret_key' => 'ISSecretKey_should_not_appear',
            'intasend_environment' => 'sandbox',
        ]);

        $response = $this->getJson('/api/health/payments');
        $content = $response->getContent();

        $this->assertStringNotContainsString('pk_test_secret_value_xyz', $content);
        $this->assertStringNotContainsString('sk_test_secret_value_abc', $content);
        $this->assertStringNotContainsString('mpesa_key_should_not_appear', $content);
        $this->assertStringNotContainsString('mpesa_secret_should_not_appear', $content);
        $this->assertStringNotContainsString('ISPubKey_should_not_appear', $content);
        $this->assertStringNotContainsString('ISSecretKey_should_not_appear', $content);
    }

    public function test_response_does_not_expose_landlord_ids(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_noids',
            'paystack_secret_key' => 'sk_test_noids',
        ]);

        $response = $this->getJson('/api/health/payments');

        $response->assertJsonMissing(['landlord_id']);
    }

    public function test_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/health/payments');
        }

        $response->assertStatus(429);
    }
}
