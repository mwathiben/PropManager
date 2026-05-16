<?php

declare(strict_types=1);

namespace Tests\Feature\PwaDepth;

use App\Models\AlertFiring;
use App\Models\Subscription;
use App\Models\SubscriptionChange;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PaystackSubscriptionService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase-37 PWA-GATEWAY-1/2/3: SubscriptionService::changePlan calls
 * Paystack on upgrade, subscription.* webhook events sync local
 * state, and gateway:proration-audit reconciles drift + fires
 * high_gateway_proration_drift sev3.
 */
class Phase37GatewayTest extends TestCase
{
    use RefreshDatabase;

    private function paystackSecret(): string
    {
        $secret = 'test-secret-key';
        config(['services.paystack.secret_key' => $secret]);

        return $secret;
    }

    private function sign(string $secret, string $body): string
    {
        return hash_hmac('sha512', $body, $secret);
    }

    public function test_change_plan_calls_paystack_on_upgrade_with_codes(): void
    {
        Http::fake([
            'api.paystack.co/subscription/SUB_test/plan' => Http::response([
                'status' => true,
                'message' => 'Subscription plan updated',
                'data' => ['plan_code' => 'PLN_new'],
            ], 200),
        ]);

        $oldPlan = SubscriptionPlan::factory()->starter()->create(['paystack_plan_code' => 'PLN_old']);
        $newPlan = SubscriptionPlan::factory()->professional()->create(['paystack_plan_code' => 'PLN_new']);
        $user = User::factory()->create(['role' => 'landlord']);
        $sub = Subscription::factory()->active()->forUser($user)->forPlan($oldPlan)->create([
            'paystack_subscription_code' => 'SUB_test',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        app(SubscriptionService::class)->changePlan($sub, $newPlan);

        $change = SubscriptionChange::query()->where('subscription_id', $sub->id)->latest('id')->first();
        $this->assertSame(SubscriptionChange::TYPE_UPGRADE, $change->change_type);
        $this->assertTrue($change->gateway_response['success']);
        $this->assertSame('PLN_new', $change->gateway_response['requested_plan']);
    }

    public function test_change_plan_records_failure_when_gateway_returns_error(): void
    {
        Http::fake([
            'api.paystack.co/subscription/SUB_fail/plan' => Http::response([
                'status' => false,
                'message' => 'Subscription not found',
            ], 404),
        ]);

        $oldPlan = SubscriptionPlan::factory()->starter()->create(['paystack_plan_code' => 'PLN_old']);
        $newPlan = SubscriptionPlan::factory()->professional()->create(['paystack_plan_code' => 'PLN_new']);
        $user = User::factory()->create(['role' => 'landlord']);
        $sub = Subscription::factory()->active()->forUser($user)->forPlan($oldPlan)->create([
            'paystack_subscription_code' => 'SUB_fail',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        app(SubscriptionService::class)->changePlan($sub, $newPlan);

        $change = SubscriptionChange::query()->where('subscription_id', $sub->id)->latest('id')->first();
        $this->assertFalse($change->gateway_response['success']);
        $this->assertSame(404, $change->gateway_response['http_status']);
        $this->assertSame($newPlan->id, $sub->fresh()->plan_id);
    }

    public function test_change_plan_skips_gateway_when_paystack_code_missing(): void
    {
        $oldPlan = SubscriptionPlan::factory()->starter()->create();
        $newPlan = SubscriptionPlan::factory()->professional()->create();
        $user = User::factory()->create(['role' => 'landlord']);
        $sub = Subscription::factory()->active()->forUser($user)->forPlan($oldPlan)->create([
            'paystack_subscription_code' => null,
        ]);

        app(SubscriptionService::class)->changePlan($sub, $newPlan);

        $change = SubscriptionChange::query()->where('subscription_id', $sub->id)->latest('id')->first();
        $this->assertNull($change->gateway_response);
    }

    public function test_subscription_disable_webhook_cancels_local_subscription(): void
    {
        $secret = $this->paystackSecret();
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->starter()->create();
        $sub = Subscription::factory()->active()->forUser($user)->forPlan($plan)->create([
            'paystack_subscription_code' => 'SUB_disable',
        ]);

        $body = json_encode([
            'event' => 'subscription.disable',
            'data' => ['subscription_code' => 'SUB_disable'],
        ]);

        $response = $this->call('POST', '/webhooks/v2/paystack', [], [], [], [
            'HTTP_X-PAYSTACK-SIGNATURE' => $this->sign($secret, $body),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();
        $fresh = $sub->fresh();
        $this->assertSame('cancelled', $fresh->status instanceof \BackedEnum ? $fresh->status->value : $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_subscription_create_webhook_activates_local_subscription(): void
    {
        $secret = $this->paystackSecret();
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->starter()->create();
        $sub = Subscription::factory()->forUser($user)->forPlan($plan)->create([
            'paystack_subscription_code' => 'SUB_create',
            'status' => 'trialing',
        ]);

        $body = json_encode([
            'event' => 'subscription.create',
            'data' => ['subscription_code' => 'SUB_create'],
        ]);

        $response = $this->call('POST', '/webhooks/v2/paystack', [], [], [], [
            'HTTP_X-PAYSTACK-SIGNATURE' => $this->sign($secret, $body),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();
        $status = $sub->fresh()->status;
        $this->assertSame('active', $status instanceof \BackedEnum ? $status->value : $status);
    }

    public function test_subscription_webhook_rejects_bad_signature(): void
    {
        $this->paystackSecret();
        $body = json_encode([
            'event' => 'subscription.disable',
            'data' => ['subscription_code' => 'SUB_x'],
        ]);

        $response = $this->call('POST', '/webhooks/v2/paystack', [], [], [], [
            'HTTP_X-PAYSTACK-SIGNATURE' => 'deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(401);
    }

    public function test_proration_audit_fires_alert_when_drift_exceeds_threshold(): void
    {
        Http::fake([
            'api.paystack.co/subscription/*' => Http::response([
                'status' => false,
                'message' => 'Not found',
            ], 404),
        ]);

        $oldPlan = SubscriptionPlan::factory()->starter()->create();
        $newPlan = SubscriptionPlan::factory()->professional()->create();
        for ($i = 0; $i < 6; $i++) {
            $user = User::factory()->create(['role' => 'landlord']);
            $sub = Subscription::factory()->active()->forUser($user)->forPlan($newPlan)->create([
                'paystack_subscription_code' => 'SUB_drift_'.$i,
            ]);
            SubscriptionChange::create([
                'subscription_id' => $sub->id,
                'from_plan_id' => $oldPlan->id,
                'to_plan_id' => $newPlan->id,
                'change_type' => SubscriptionChange::TYPE_UPGRADE,
                'prorated_amount_kes' => 100,
                'effective_at' => now()->subHours(2),
                'gateway_response' => null,
            ]);
        }

        $this->artisan('gateway:proration-audit', ['--threshold' => 5])->assertExitCode(0);

        $alert = AlertFiring::query()->where('alert_key', 'high_gateway_proration_drift')->first();
        $this->assertNotNull($alert);
        $this->assertNull($alert->resolved_at);
    }

    public function test_proration_audit_resolves_alert_when_below_threshold(): void
    {
        $oldPlan = SubscriptionPlan::factory()->starter()->create();
        $newPlan = SubscriptionPlan::factory()->professional()->create();
        $user = User::factory()->create(['role' => 'landlord']);
        $sub = Subscription::factory()->active()->forUser($user)->forPlan($newPlan)->create([
            'paystack_subscription_code' => 'SUB_ok',
        ]);
        SubscriptionChange::create([
            'subscription_id' => $sub->id,
            'from_plan_id' => $oldPlan->id,
            'to_plan_id' => $newPlan->id,
            'change_type' => SubscriptionChange::TYPE_UPGRADE,
            'prorated_amount_kes' => 100,
            'effective_at' => now()->subHour(),
            'gateway_response' => ['success' => true, 'http_status' => 200, 'plan_code' => 'PLN_x'],
        ]);

        AlertFiring::create([
            'alert_key' => 'high_gateway_proration_drift',
            'severity' => 'sev3',
            'value' => 10,
            'threshold' => 5,
            'fired_at' => now()->subHour(),
            'metadata' => [],
        ]);

        $this->artisan('gateway:proration-audit', ['--threshold' => 5])->assertExitCode(0);

        $alert = AlertFiring::query()->where('alert_key', 'high_gateway_proration_drift')->latest('id')->first();
        $this->assertNotNull($alert->resolved_at);
    }

    public function test_proration_audit_handles_empty_changes_table(): void
    {
        $this->artisan('gateway:proration-audit')->assertExitCode(0);
        $this->assertDatabaseMissing('alert_firings', ['alert_key' => 'high_gateway_proration_drift', 'resolved_at' => null]);
    }
}
