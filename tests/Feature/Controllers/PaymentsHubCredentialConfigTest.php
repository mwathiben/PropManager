<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Payments Hub is the canonical home for payment-gateway configuration.
 * These verify the Collection tab renders the masked credential config and
 * that the canonical PaymentMethodConfigService secret-handling (last-4
 * masking, blank-preserves-existing, never-expose-raw) is in force through
 * the payments-hub endpoints — the security-critical guarantees the config
 * move must preserve.
 */
class PaymentsHubCredentialConfigTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private PaymentConfiguration $paymentConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $data = $this->createLandlordWithFullSetup();
        $this->landlord = $data['landlord'];

        $this->paymentConfig = PaymentConfiguration::factory()->create([
            'landlord_id' => $this->landlord->id,
            'paystack_secret_key' => 'sk_test_secret12345678',
            'paystack_public_key' => 'pk_test_public',
            'mpesa_consumer_key' => 'consumer_key_abcd1234',
            'mpesa_consumer_secret' => 'consumer_secret_xyz9876',
            'intasend_secret_key' => 'intasend_secret_efgh',
        ]);
    }

    public function test_collection_tab_renders_inertia_page(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('payments-hub.collection'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PaymentsHub/Index')
            ->has('paymentConfig')
            ->has('paymentMethods')
            ->has('payoutAccounts')
            ->has('billingSettings')
        );
    }

    public function test_collection_tab_returns_last4_for_paystack_secret(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('payments-hub.collection'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('paymentConfig.paystack_secret_key_last4')
            ->where('paymentConfig.paystack_secret_key_last4', '****5678')
        );
    }

    public function test_collection_tab_never_exposes_raw_secrets(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('payments-hub.collection'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->missing('paymentConfig.paystack_secret_key')
            ->missing('paymentConfig.mpesa_consumer_key')
            ->missing('paymentConfig.mpesa_consumer_secret')
            ->missing('paymentConfig.mpesa_passkey')
            ->missing('paymentConfig.mpesa_b2c_password')
            ->missing('paymentConfig.mpesa_b2c_security_credential')
            ->missing('paymentConfig.intasend_secret_key')
            ->missing('paymentConfig.intasend_webhook_challenge')
        );
    }

    public function test_collection_tab_returns_last4_for_mpesa_consumer_key(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('payments-hub.collection'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('paymentConfig.mpesa_consumer_key_last4', '****1234')
        );
    }

    public function test_update_payment_methods_uses_canonical_blank_preserve_logic(): void
    {
        $originalSecret = $this->paymentConfig->paystack_secret_key;

        $this->actingAs($this->landlord)
            ->post(route('payments-hub.payment-methods.update'), [
                'accepted_payment_methods' => ['cash', 'paystack'],
                'paystack_enabled' => true,
                'paystack_public_key' => 'pk_test_new_key',
                'paystack_secret_key' => '',  // blank — should preserve existing
            ]);

        $this->paymentConfig->refresh();

        $this->assertSame($originalSecret, $this->paymentConfig->paystack_secret_key);
        $this->assertSame('pk_test_new_key', $this->paymentConfig->paystack_public_key);
    }

    public function test_update_payment_methods_overwrites_secret_when_provided(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('payments-hub.payment-methods.update'), [
                'accepted_payment_methods' => ['cash', 'paystack'],
                'paystack_enabled' => true,
                'paystack_public_key' => 'pk_test_existing',
                'paystack_secret_key' => 'sk_test_brand_new_9999',
            ]);

        $this->paymentConfig->refresh();

        $this->assertSame('sk_test_brand_new_9999', $this->paymentConfig->paystack_secret_key);
    }

    public function test_unauthenticated_user_cannot_access_collection_tab(): void
    {
        $response = $this->get(route('payments-hub.collection'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_landlord_cannot_update_payment_methods(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $response = $this->actingAs($tenant)
            ->post(route('payments-hub.payment-methods.update'), [
                'accepted_payment_methods' => ['cash'],
            ]);

        $response->assertStatus(403);
    }
}
