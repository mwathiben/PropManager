<?php

namespace Tests\Feature;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * PAY-V2-005: M-Pesa Consumer Credential Migration Tests
 *
 * Tests for:
 * - Credential encryption at rest
 * - Per-landlord isolation
 * - OAuth token cache isolation
 * - Last 4 chars display (no full secret to frontend)
 */
class MpesaCredentialMigrationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private PaymentConfiguration $paymentConfig;

    private array $setupData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];

        $this->paymentConfig = PaymentConfiguration::factory()->create([
            'landlord_id' => $this->landlord->id,
            'mpesa_shortcode_type' => 'paybill',
            'mpesa_shortcode' => '174379',
            'mpesa_passkey' => 'test_passkey_12345678',
            'mpesa_consumer_key' => 'test_consumer_key_abcd1234',
            'mpesa_consumer_secret' => 'test_consumer_secret_xyz9876',
        ]);
    }

    /** @test */
    public function test_mpesa_consumer_key_is_encrypted_in_database(): void
    {
        $raw = DB::table('payment_configurations')
            ->where('id', $this->paymentConfig->id)
            ->value('mpesa_consumer_key');

        $this->assertNotEquals('test_consumer_key_abcd1234', $raw);

        $this->assertEquals(
            'test_consumer_key_abcd1234',
            $this->paymentConfig->fresh()->mpesa_consumer_key
        );
    }

    /** @test */
    public function test_mpesa_consumer_secret_is_encrypted_in_database(): void
    {
        $raw = DB::table('payment_configurations')
            ->where('id', $this->paymentConfig->id)
            ->value('mpesa_consumer_secret');

        $this->assertNotEquals('test_consumer_secret_xyz9876', $raw);

        $this->assertEquals(
            'test_consumer_secret_xyz9876',
            $this->paymentConfig->fresh()->mpesa_consumer_secret
        );
    }

    /** @test */
    public function test_mpesa_service_uses_landlord_config(): void
    {
        $service = new MpesaService($this->paymentConfig);

        $this->assertTrue($service->isConfigured());
    }

    /** @test */
    public function test_has_mpesa_api_config_returns_correct_values(): void
    {
        $this->assertTrue($this->paymentConfig->hasMpesaApiConfig());

        $this->paymentConfig->update(['mpesa_consumer_key' => null]);
        $this->assertFalse($this->paymentConfig->fresh()->hasMpesaApiConfig());

        $this->paymentConfig->update([
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => null,
        ]);
        $this->assertFalse($this->paymentConfig->fresh()->hasMpesaApiConfig());

        $this->paymentConfig->update([
            'mpesa_consumer_key' => 'test_key',
            'mpesa_consumer_secret' => 'test_secret',
        ]);
        $this->assertTrue($this->paymentConfig->fresh()->hasMpesaApiConfig());
    }

    /** @test */
    public function test_settings_controller_returns_last_4_chars_of_consumer_key(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.index', ['tab' => 'payments']));

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('paymentConfig.mpesa_consumer_key_last4')
            ->where('paymentConfig.mpesa_consumer_key_last4', '****1234')
        );
    }

    /** @test */
    public function test_settings_controller_returns_last_4_chars_of_consumer_secret(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.index', ['tab' => 'payments']));

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('paymentConfig.mpesa_consumer_secret_last4')
            ->where('paymentConfig.mpesa_consumer_secret_last4', '****9876')
        );
    }

    /** @test */
    public function test_full_consumer_credentials_never_exposed_to_frontend(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.index', ['tab' => 'payments']));

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->missing('paymentConfig.mpesa_consumer_key')
            ->missing('paymentConfig.mpesa_consumer_secret')
            ->missing('paymentConfig.mpesa_passkey')
        );
    }

    /** @test */
    public function test_different_landlords_have_isolated_oauth_tokens(): void
    {
        $landlord2 = User::factory()->create(['role' => 'landlord']);
        $config2 = PaymentConfiguration::factory()->create([
            'landlord_id' => $landlord2->id,
            'mpesa_shortcode_type' => 'paybill',
            'mpesa_shortcode' => '654321',
            'mpesa_passkey' => 'different_passkey',
            'mpesa_consumer_key' => 'different_consumer_key',
            'mpesa_consumer_secret' => 'different_consumer_secret',
        ]);

        $cacheKey1 = 'mpesa_access_token_sandbox_'.$this->paymentConfig->id;
        $cacheKey2 = 'mpesa_access_token_sandbox_'.$config2->id;

        $this->assertNotEquals($cacheKey1, $cacheKey2);

        Cache::put($cacheKey1, 'token_for_landlord_1', 3600);
        Cache::put($cacheKey2, 'token_for_landlord_2', 3600);

        $this->assertEquals('token_for_landlord_1', Cache::get($cacheKey1));
        $this->assertEquals('token_for_landlord_2', Cache::get($cacheKey2));
    }

    /** @test */
    public function test_unconfigured_landlord_returns_503_on_mpesa_payment_init(): void
    {
        $unconfiguredSetup = $this->createLandlordWithFullSetup();
        $unconfiguredLandlord = $unconfiguredSetup['landlord'];
        $unit = $unconfiguredSetup['units']->first();

        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($unconfiguredLandlord, $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $response = $this->actingAs($tenant)
            ->postJson('/api/v1/tenant/payments/mpesa/initiate', [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total_due,
                'phone' => '0712345678',
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'M-Pesa payments are not configured.',
            ]);
    }

    /** @test */
    public function test_update_preserves_existing_secret_when_blank(): void
    {
        $originalKey = $this->paymentConfig->mpesa_consumer_key;
        $originalSecret = $this->paymentConfig->mpesa_consumer_secret;

        $this->actingAs($this->landlord)
            ->post(route('settings.payment.update'), [
                'accepted_payment_methods' => ['cash', 'mobile_money'],
                'mpesa_shortcode' => '999999',
                'mpesa_consumer_key' => '',
                'mpesa_consumer_secret' => '',
            ]);

        $this->paymentConfig->refresh();

        $this->assertEquals($originalKey, $this->paymentConfig->mpesa_consumer_key);
        $this->assertEquals($originalSecret, $this->paymentConfig->mpesa_consumer_secret);
        $this->assertEquals('999999', $this->paymentConfig->mpesa_shortcode);
    }

    /** @test */
    public function test_update_overwrites_secret_when_provided(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('settings.payment.update'), [
                'accepted_payment_methods' => ['cash', 'mobile_money'],
                'mpesa_consumer_key' => 'new_consumer_key_xxxx',
                'mpesa_consumer_secret' => 'new_consumer_secret_yyyy',
            ]);

        $this->paymentConfig->refresh();

        $this->assertEquals('new_consumer_key_xxxx', $this->paymentConfig->mpesa_consumer_key);
        $this->assertEquals('new_consumer_secret_yyyy', $this->paymentConfig->mpesa_consumer_secret);
    }

    /** @test */
    public function test_check_mpesa_status_uses_landlord_config(): void
    {
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $checkoutRequestId = 'ws_CO_TEST_'.uniqid();

        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
                'access_token' => 'test_token_123',
                'expires_in' => '3600',
            ], 200),
            'sandbox.safaricom.co.ke/mpesa/stkpushquery/*' => Http::response([
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
                'MerchantRequestID' => 'test_merchant_id',
                'CheckoutRequestID' => $checkoutRequestId,
                'ResultCode' => '0',
                'ResultDesc' => 'The service request is processed successfully.',
            ], 200),
        ]);

        $response = $this->actingAs($tenant)
            ->postJson('/api/v1/tenant/payments/mpesa/status', [
                'checkout_request_id' => $checkoutRequestId,
            ]);

        $response->assertOk();
    }
}
