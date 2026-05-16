<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-40 GATEWAY-WEBHOOK-1/2/3: StripeWebhookController +
 * /webhooks/v2/stripe route + subscription lifecycle event handlers.
 */
class Phase40StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->secret]);
    }

    private function signPayload(array $payload, ?string $secret = null, ?int $timestamp = null): string
    {
        $secret = $secret ?? $this->secret;
        $timestamp = $timestamp ?? time();
        $payloadJson = json_encode($payload);
        $signedPayload = $timestamp.'.'.$payloadJson;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    public function test_route_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('webhooks.v2.stripe'));
    }

    public function test_returns_503_when_secret_not_configured(): void
    {
        config(['services.stripe.webhook_secret' => '']);

        $payload = ['id' => 'evt_1', 'type' => 'ping', 'data' => ['object' => []]];
        $response = $this->postJson('/webhooks/v2/stripe', $payload, [
            'Stripe-Signature' => 't=1,v1=fake',
        ]);

        $response->assertStatus(503);
        $response->assertJson(['error' => 'not_configured']);
    }

    public function test_returns_401_on_invalid_signature(): void
    {
        $payload = ['id' => 'evt_1', 'type' => 'ping', 'data' => ['object' => []]];

        $response = $this->postJson('/webhooks/v2/stripe', $payload, [
            'Stripe-Signature' => 't=1,v1=invalid_sig_bytes',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'invalid_signature']);
    }

    public function test_accepts_valid_signature_and_returns_200(): void
    {
        $payload = [
            'id' => 'evt_test_'.uniqid(),
            'type' => 'ping',
            'data' => ['object' => []],
        ];

        $response = $this->call(
            'POST',
            '/webhooks/v2/stripe',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->signPayload($payload)],
            json_encode($payload),
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'accepted']);
    }

    public function test_duplicate_event_returns_duplicate_status(): void
    {
        $payload = [
            'id' => 'evt_dup_'.uniqid(),
            'type' => 'ping',
            'data' => ['object' => []],
        ];
        $sig = $this->signPayload($payload);

        // First delivery
        $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $sig],
            json_encode($payload));

        // Second delivery — same id should be deduped
        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $sig],
            json_encode($payload));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'duplicate']);
    }

    public function test_subscription_created_flips_local_status_to_active(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $landlord->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'stripe_subscription_code' => 'sub_test_active_'.uniqid(),
        ]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'customer.subscription.created',
            'data' => ['object' => [
                'id' => $sub->stripe_subscription_code,
                'status' => 'active',
            ]],
        ];

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->signPayload($payload)],
            json_encode($payload));

        $response->assertStatus(200);
        $this->assertSame('active', $sub->fresh()->status->value ?? $sub->fresh()->status);
    }

    public function test_subscription_deleted_flips_local_status_to_cancelled(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $landlord->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_code' => 'sub_test_cancel_'.uniqid(),
        ]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => $sub->stripe_subscription_code]],
        ];

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->signPayload($payload)],
            json_encode($payload));

        $response->assertStatus(200);
        $fresh = $sub->fresh();
        $this->assertSame('cancelled', $fresh->status->value ?? $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
    }
}
