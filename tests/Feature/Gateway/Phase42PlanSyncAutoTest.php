<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Enums\DriftResolveMode;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDriftLog;
use App\Models\User;
use App\Services\Subscriptions\PlanDriftResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-42 PLAN-SYNC-AUTO-1/2/3: drift_resolve_mode enum +
 * PlanDriftResolver service + subscription_plan_drift_log table +
 * admin endpoint.
 */
class Phase42PlanSyncAutoTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_plans_has_drift_resolve_mode_column(): void
    {
        $this->assertContains('drift_resolve_mode', Schema::getColumnListing('subscription_plans'));
    }

    public function test_subscription_plan_drift_log_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('subscription_plan_drift_log'));
        $cols = Schema::getColumnListing('subscription_plan_drift_log');
        foreach (['subscription_plan_id', 'stripe_price_id', 'app_price_cents', 'stripe_price_cents', 'drift_resolve_mode_at_time', 'resolution', 'detected_at', 'resolved_at'] as $c) {
            $this->assertContains($c, $cols);
        }
    }

    public function test_drift_resolve_mode_defaults_to_manual_review(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $plan->refresh();
        $this->assertSame(DriftResolveMode::ManualReview, $plan->drift_resolve_mode);
    }

    public function test_resolver_manual_review_writes_manual_pending_log(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'stripe_plan_code' => 'price_test_'.uniqid(),
            'price_monthly' => 1000.00,
            'drift_resolve_mode' => DriftResolveMode::ManualReview->value,
        ]);

        $resolver = app(PlanDriftResolver::class);
        $log = $resolver->resolve($plan, 150000, $plan->stripe_plan_code);

        $this->assertSame(SubscriptionPlanDriftLog::RESOLUTION_MANUAL_PENDING, $log->resolution);
        $this->assertNull($log->resolved_at);
        $this->assertSame(100000, $log->app_price_cents);
        $this->assertSame(150000, $log->stripe_price_cents);
        // Plan price NOT modified for manual_review
        $plan->refresh();
        $this->assertSame('1000.00', $plan->price_monthly);
    }

    public function test_resolver_always_stripe_wins_writes_back_plan_price(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'stripe_plan_code' => 'price_test_'.uniqid(),
            'price_monthly' => 1000.00,
            'drift_resolve_mode' => DriftResolveMode::AlwaysStripeWins->value,
        ]);

        $resolver = app(PlanDriftResolver::class);
        $log = $resolver->resolve($plan, 150000, $plan->stripe_plan_code);

        $this->assertSame(SubscriptionPlanDriftLog::RESOLUTION_STRIPE_WINS, $log->resolution);
        $this->assertNotNull($log->resolved_at);
        $plan->refresh();
        $this->assertSame('1500.00', $plan->price_monthly);
    }

    public function test_resolver_always_app_wins_records_log_without_calling_unconfigured_stripe(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'stripe_plan_code' => 'price_test_'.uniqid(),
            'price_monthly' => 1000.00,
            'drift_resolve_mode' => DriftResolveMode::AlwaysAppWins->value,
        ]);

        // StripeSubscriptionService is unconfigured by default in tests —
        // the resolver still records the log; the actual Stripe push noops.
        $resolver = app(PlanDriftResolver::class);
        $log = $resolver->resolve($plan, 150000, $plan->stripe_plan_code);

        $this->assertSame(SubscriptionPlanDriftLog::RESOLUTION_APP_WINS, $log->resolution);
        $this->assertNotNull($log->resolved_at);
        $plan->refresh();
        $this->assertSame('1000.00', $plan->price_monthly);
    }

    public function test_price_updated_webhook_appends_drift_log_row(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_resolver_'.uniqid()]);

        $plan = SubscriptionPlan::factory()->create([
            'stripe_plan_code' => 'price_test_'.uniqid(),
            'price_monthly' => 1000.00,
            'drift_resolve_mode' => DriftResolveMode::AlwaysStripeWins->value,
        ]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'price.updated',
            'data' => ['object' => [
                'id' => $plan->stripe_plan_code,
                'unit_amount' => 175000, // 1750.00 — diverges from 1000
                'currency' => 'usd',
            ]],
        ];

        $sig = $this->signPayload($payload, config('services.stripe.webhook_secret'));

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $sig],
            json_encode($payload));

        $response->assertStatus(200);
        $this->assertSame(1, SubscriptionPlanDriftLog::where('subscription_plan_id', $plan->id)->count());
        $log = SubscriptionPlanDriftLog::where('subscription_plan_id', $plan->id)->first();
        $this->assertSame(SubscriptionPlanDriftLog::RESOLUTION_STRIPE_WINS, $log->resolution);
        $plan->refresh();
        $this->assertSame('1750.00', $plan->price_monthly);
    }

    public function test_admin_can_update_plan_drift_resolve_mode(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $plan = SubscriptionPlan::factory()->create([
            'stripe_plan_code' => 'price_admin_'.uniqid(),
            'drift_resolve_mode' => DriftResolveMode::ManualReview->value,
        ]);

        $response = $this->actingAs($admin)
            ->post("/admin/gateways/plan-drift-mode/{$plan->id}", [
                'drift_resolve_mode' => DriftResolveMode::AlwaysStripeWins->value,
            ]);

        $response->assertRedirect();
        $plan->refresh();
        $this->assertSame(DriftResolveMode::AlwaysStripeWins, $plan->drift_resolve_mode);
    }

    public function test_admin_drift_resolve_mode_rejects_unknown_value(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $plan = SubscriptionPlan::factory()->create([
            'stripe_plan_code' => 'price_invalid_'.uniqid(),
        ]);

        $response = $this->actingAs($admin)
            ->from('/admin/gateways')
            ->post("/admin/gateways/plan-drift-mode/{$plan->id}", [
                'drift_resolve_mode' => 'always_chaos_wins',
            ]);

        $response->assertSessionHasErrors('drift_resolve_mode');
    }

    private function signPayload(array $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.json_encode($payload);

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret);
    }
}
