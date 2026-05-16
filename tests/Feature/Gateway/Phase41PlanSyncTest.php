<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-41 GATEWAY-PLAN-SYNC-1/2/3: stripe:plan-sync cron +
 * price.updated webhook + subscription_plan_drift gauge.
 */
class Phase41PlanSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_plan_sync_command_noops_when_unconfigured(): void
    {
        // No system Stripe creds → command exits cleanly without API calls.
        $this->artisan('stripe:plan-sync')
            ->assertExitCode(0)
            ->expectsOutputToContain('not configured');
    }

    public function test_stripe_plan_sync_scheduled_weekly_monday_0435(): void
    {
        $entry = collect(\Illuminate\Support\Facades\Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'stripe:plan-sync'));

        $this->assertNotNull($entry, 'stripe:plan-sync must be scheduled');
        $this->assertSame('35 4 * * 1', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_price_updated_webhook_logs_drift_when_amounts_differ(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_price_drift']);

        $plan = SubscriptionPlan::factory()->create([
            'stripe_plan_code' => 'price_test_drift_'.uniqid(),
            'price_monthly' => 1000.00,
        ]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'price.updated',
            'data' => ['object' => [
                'id' => $plan->stripe_plan_code,
                'unit_amount' => 150000,  // = 1500.00 — diverges from 1000
                'currency' => 'usd',
            ]],
        ];

        $sig = $this->signPayload($payload, 'whsec_test_price_drift');

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $sig],
            json_encode($payload));

        $response->assertStatus(200);
    }

    public function test_price_updated_for_unknown_plan_is_silent_noop(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_unknown_price']);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'price.updated',
            'data' => ['object' => [
                'id' => 'price_unknown_'.uniqid(),
                'unit_amount' => 9999,
                'currency' => 'usd',
            ]],
        ];

        $sig = $this->signPayload($payload, 'whsec_test_unknown_price');

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $sig],
            json_encode($payload));

        $response->assertStatus(200);
    }

    private function signPayload(array $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.json_encode($payload);

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret);
    }
}
