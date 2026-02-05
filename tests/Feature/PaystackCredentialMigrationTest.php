<?php

namespace Tests\Feature;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * PAY-V2-004: Paystack Credential Migration Tests
 *
 * Tests for:
 * - Credential encryption at rest
 * - Per-landlord isolation
 * - Webhook security (per-landlord signature verification)
 * - Last 4 chars display (no full secret to frontend)
 */
class PaystackCredentialMigrationTest extends TestCase
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
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_abcdef123456789',
            'paystack_secret_key' => 'sk_test_secretkey12345678',
        ]);
    }

    public function test_paystack_secret_key_is_encrypted_in_database(): void
    {
        // Get raw value from database (bypassing Eloquent cast)
        $raw = DB::table('payment_configurations')
            ->where('id', $this->paymentConfig->id)
            ->value('paystack_secret_key');

        // Raw value should NOT be the plaintext secret
        $this->assertNotEquals('sk_test_secretkey12345678', $raw);

        // But decrypted value should match
        $this->assertEquals(
            'sk_test_secretkey12345678',
            $this->paymentConfig->fresh()->paystack_secret_key
        );
    }

    public function test_paystack_service_uses_landlord_config(): void
    {
        $service = new PaystackService($this->paymentConfig);

        $this->assertTrue($service->isConfigured());
        $this->assertEquals('pk_test_abcdef123456789', $service->getPublicKey());
    }

    public function test_has_paystack_config_returns_correct_values(): void
    {
        // Fully configured
        $this->assertTrue($this->paymentConfig->hasPaystackConfig());

        // Missing public key
        $this->paymentConfig->update(['paystack_public_key' => null]);
        $this->assertFalse($this->paymentConfig->fresh()->hasPaystackConfig());

        // Missing secret key
        $this->paymentConfig->update([
            'paystack_public_key' => 'pk_test_xxx',
            'paystack_secret_key' => null,
        ]);
        $this->assertFalse($this->paymentConfig->fresh()->hasPaystackConfig());

        // Disabled
        $this->paymentConfig->update([
            'paystack_secret_key' => 'sk_test_xxx',
            'paystack_enabled' => false,
        ]);
        $this->assertFalse($this->paymentConfig->fresh()->hasPaystackConfig());
    }

    public function test_settings_controller_returns_last_4_chars_of_secret_key(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.index', ['tab' => 'payments']));

        $response->assertOk();

        // Should have last 4 chars masked
        $response->assertInertia(fn ($page) => $page
            ->has('paymentConfig.paystack_secret_key_last4')
            ->where('paymentConfig.paystack_secret_key_last4', '****5678')
        );
    }

    public function test_full_secret_key_never_exposed_to_frontend(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.index', ['tab' => 'payments']));

        $response->assertOk();

        // Full secret should NOT be in the response
        $response->assertInertia(fn ($page) => $page
            ->missing('paymentConfig.paystack_secret_key')
            ->missing('paymentConfig.mpesa_consumer_key')
            ->missing('paymentConfig.mpesa_consumer_secret')
            ->missing('paymentConfig.intasend_secret_key')
            ->missing('paymentConfig.intasend_webhook_challenge')
        );
    }

    public function test_webhook_verifies_with_correct_landlord_secret(): void
    {
        // Create tenant and lease with invoice
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $webhookData = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PAY_'.uniqid(),
                'amount' => 500000, // 5000 in kobo
                'status' => 'success',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'landlord_id' => $this->landlord->id,
                ],
            ],
        ];

        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha512', $payload, 'sk_test_secretkey12345678');

        $response = $this->postJson('/webhooks/paystack', $webhookData, [
            'x-paystack-signature' => $signature,
            'Content-Type' => 'application/json',
        ]);

        // Should not get 401 (signature verification passed)
        $response->assertStatus(200);
    }

    public function test_webhook_rejects_missing_landlord_id(): void
    {
        $webhookData = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PAY_'.uniqid(),
                'amount' => 500000,
                'status' => 'success',
                'metadata' => [
                    'invoice_id' => 1,
                    // Missing landlord_id
                ],
            ],
        ];

        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha512', $payload, 'sk_test_secretkey12345678');

        $response = $this->postJson('/webhooks/paystack', $webhookData, [
            'x-paystack-signature' => $signature,
        ]);

        // Should reject with 400 (missing landlord context)
        $response->assertStatus(400)
            ->assertJson(['error' => 'Missing landlord context']);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        // Create tenant and lease with invoice
        $unit = $this->setupData['units']->first();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease);

        $webhookData = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PAY_'.uniqid(),
                'amount' => 500000,
                'status' => 'success',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'landlord_id' => $this->landlord->id,
                ],
            ],
        ];

        // Use wrong secret key for signature
        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha512', $payload, 'wrong_secret_key');

        $response = $this->postJson('/webhooks/paystack', $webhookData, [
            'x-paystack-signature' => $signature,
        ]);

        // Should reject with 401 (invalid signature)
        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_update_preserves_existing_secret_when_blank(): void
    {
        $originalSecret = $this->paymentConfig->paystack_secret_key;

        $this->actingAs($this->landlord)
            ->post(route('settings.payment.update'), [
                'accepted_payment_methods' => ['cash', 'paystack'],
                'paystack_enabled' => true,
                'paystack_public_key' => 'pk_test_new_public_key',
                'paystack_secret_key' => '', // Empty - should preserve existing
            ]);

        $this->paymentConfig->refresh();

        // Secret should be preserved (not overwritten with empty)
        $this->assertEquals($originalSecret, $this->paymentConfig->paystack_secret_key);
        // Public key should be updated
        $this->assertEquals('pk_test_new_public_key', $this->paymentConfig->paystack_public_key);
    }

    public function test_update_overwrites_secret_when_provided(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('settings.payment.update'), [
                'accepted_payment_methods' => ['cash', 'paystack'],
                'paystack_enabled' => true,
                'paystack_public_key' => 'pk_test_abcdef123456789',
                'paystack_secret_key' => 'sk_test_new_secret_key_9999',
            ]);

        $this->paymentConfig->refresh();

        // Secret should be updated
        $this->assertEquals('sk_test_new_secret_key_9999', $this->paymentConfig->paystack_secret_key);
    }

    public function test_different_landlords_have_isolated_credentials(): void
    {
        // Create a second landlord with different credentials
        $landlord2 = User::factory()->create(['role' => 'landlord']);
        $config2 = PaymentConfiguration::factory()->create([
            'landlord_id' => $landlord2->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_different_key',
            'paystack_secret_key' => 'sk_test_different_secret',
        ]);

        // Create services for each landlord
        $service1 = new PaystackService($this->paymentConfig);
        $service2 = new PaystackService($config2);

        // Keys should be different
        $this->assertNotEquals($service1->getPublicKey(), $service2->getPublicKey());

        // Verify signature for landlord 1 should not work with landlord 2's secret
        $payload = '{"test":"data"}';
        $signature1 = hash_hmac('sha512', $payload, 'sk_test_secretkey12345678');

        $this->assertTrue($service1->verifyWebhookSignature($payload, $signature1));
        $this->assertFalse($service2->verifyWebhookSignature($payload, $signature1));
    }

    public function test_unconfigured_landlord_returns_503_on_payment_init(): void
    {
        // Create landlord without Paystack config using helper
        $unconfiguredSetup = $this->createLandlordWithFullSetup();
        $unconfiguredLandlord = $unconfiguredSetup['landlord'];
        $unit = $unconfiguredSetup['units']->first();

        // Create tenant with active lease
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($unconfiguredLandlord, $unit);
        $invoice = $this->createInvoiceForLease($lease);

        // Attempt to initiate Paystack payment (no PaymentConfiguration for this landlord)
        $response = $this->actingAs($tenant)
            ->postJson('/api/v1/tenant/payments/paystack/initiate', [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total_due,
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Online card payments are not configured.',
            ]);
    }
}
