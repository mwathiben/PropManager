<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\StripeCustomer;
use App\Models\User;
use App\Services\StripeCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-42 METHODS-1/2/3: stripe_customers table +
 * StripeCustomerService idempotent ensureCustomer + customer.*
 * webhook handlers.
 */
class Phase42MethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_customers_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('stripe_customers'));
        $cols = Schema::getColumnListing('stripe_customers');
        foreach (['user_id', 'stripe_customer_id', 'default_payment_method_id', 'deleted_at'] as $c) {
            $this->assertContains($c, $cols);
        }
    }

    public function test_service_unconfigured_by_default(): void
    {
        $this->assertFalse((new StripeCustomerService)->isConfigured());
    }

    public function test_ensure_customer_returns_existing_mapping_without_calling_stripe(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        StripeCustomer::create([
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_existing_'.uniqid(),
        ]);

        $service = new StripeCustomerService;
        $id = $service->ensureCustomer($user);

        $this->assertNotNull($id);
        $this->assertStringStartsWith('cus_existing_', $id);
    }

    public function test_ensure_customer_returns_null_when_unconfigured_and_no_mapping(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $service = new StripeCustomerService;
        $this->assertNull($service->ensureCustomer($user));
        $this->assertSame(0, StripeCustomer::query()->count());
    }

    public function test_find_mapping_returns_null_for_unmapped_user(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $this->assertNull((new StripeCustomerService)->findMapping($user));
    }

    public function test_user_id_uniqueness_prevents_duplicate_mappings(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        StripeCustomer::create(['user_id' => $user->id, 'stripe_customer_id' => 'cus_a']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        StripeCustomer::create(['user_id' => $user->id, 'stripe_customer_id' => 'cus_b']);
    }

    public function test_customer_created_webhook_upserts_mapping(): void
    {
        $secret = 'whsec_test_cust_created_'.uniqid();
        config(['services.stripe.webhook_secret' => $secret]);

        $user = User::factory()->create(['role' => 'landlord']);
        $customerId = 'cus_test_'.uniqid();
        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'customer.created',
            'data' => ['object' => [
                'id' => $customerId,
                'metadata' => ['user_id' => $user->id],
            ]],
        ];

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->sign($payload, $secret)],
            json_encode($payload));

        $response->assertStatus(200);
        $mapping = StripeCustomer::where('user_id', $user->id)->first();
        $this->assertNotNull($mapping);
        $this->assertSame($customerId, $mapping->stripe_customer_id);
    }

    public function test_customer_updated_webhook_syncs_default_payment_method(): void
    {
        $secret = 'whsec_test_cust_updated_'.uniqid();
        config(['services.stripe.webhook_secret' => $secret]);

        $user = User::factory()->create(['role' => 'landlord']);
        $customerId = 'cus_test_'.uniqid();
        StripeCustomer::create(['user_id' => $user->id, 'stripe_customer_id' => $customerId]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'customer.updated',
            'data' => ['object' => [
                'id' => $customerId,
                'invoice_settings' => ['default_payment_method' => 'pm_card_test_xyz'],
            ]],
        ];

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->sign($payload, $secret)],
            json_encode($payload));

        $response->assertStatus(200);
        $mapping = StripeCustomer::where('user_id', $user->id)->first();
        $this->assertSame('pm_card_test_xyz', $mapping->default_payment_method_id);
    }

    public function test_customer_deleted_webhook_soft_deletes_mapping(): void
    {
        $secret = 'whsec_test_cust_deleted_'.uniqid();
        config(['services.stripe.webhook_secret' => $secret]);

        $user = User::factory()->create(['role' => 'landlord']);
        $customerId = 'cus_test_'.uniqid();
        StripeCustomer::create(['user_id' => $user->id, 'stripe_customer_id' => $customerId]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'customer.deleted',
            'data' => ['object' => ['id' => $customerId]],
        ];

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->sign($payload, $secret)],
            json_encode($payload));

        $response->assertStatus(200);
        // Soft-deleted: not in default query, but visible withTrashed.
        $this->assertNull(StripeCustomer::where('user_id', $user->id)->first());
        $this->assertNotNull(StripeCustomer::withTrashed()->where('user_id', $user->id)->first());
    }

    private function sign(array $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.json_encode($payload);

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret);
    }
}
