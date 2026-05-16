<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\AlertFiring;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Growth\ChurnService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase34ChurnTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_persists_reason_and_feedback(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $sub = Subscription::factory()->active()->forPlan($plan)->create();

        app(SubscriptionService::class)->cancel($sub, true, 'too_expensive', 'Plan jumped past my budget.');

        $sub->refresh();
        $this->assertSame('too_expensive', $sub->cancel_reason);
        $this->assertSame('Plan jumped past my budget.', $sub->cancel_feedback);
        $this->assertNotNull($sub->cancelled_at);
    }

    public function test_cancel_rejects_unknown_reason(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $sub = Subscription::factory()->active()->forPlan($plan)->create();

        $this->expectException(\InvalidArgumentException::class);
        app(SubscriptionService::class)->cancel($sub, true, 'frodo_left_for_a_better_landlord');
    }

    public function test_cancel_accepts_null_reason(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $sub = Subscription::factory()->active()->forPlan($plan)->create();

        app(SubscriptionService::class)->cancel($sub, true);

        $sub->refresh();
        $this->assertNull($sub->cancel_reason);
        $this->assertNotNull($sub->cancelled_at);
    }

    public function test_monthly_churn_rate_computes_correctly(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        // 4 active at start of last month, 1 churned during it.
        for ($i = 0; $i < 3; $i++) {
            Subscription::factory()->active()->forPlan($plan)->create([
                'created_at' => $lastMonth->copy()->subMonth(),
            ]);
        }
        Subscription::factory()->forPlan($plan)->create([
            'created_at' => $lastMonth->copy()->subMonth(),
            'status' => 'cancelled',
            'cancelled_at' => $lastMonth->copy()->addDays(10),
        ]);

        $rate = app(ChurnService::class)->monthlyChurnRate($lastMonth);
        $this->assertEqualsWithDelta(0.25, $rate, 0.01);
    }

    public function test_subscription_cohorts_buckets_by_cohort_month(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $cohortA = Carbon::now()->subMonthsNoOverflow(2)->startOfMonth();
        $cohortB = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        Subscription::factory()->active()->forPlan($plan)->count(3)->create([
            'created_at' => $cohortA->copy()->addDay(),
        ]);
        Subscription::factory()->active()->forPlan($plan)->count(2)->create([
            'created_at' => $cohortB->copy()->addDay(),
        ]);

        $matrix = app(ChurnService::class)->subscriptionCohorts(6);

        $this->assertGreaterThanOrEqual(2, count($matrix));
        $cohortAEntry = collect($matrix)->firstWhere('cohort_month', $cohortA->format('Y-m'));
        $this->assertSame(3, $cohortAEntry['size']);
        $this->assertEqualsWithDelta(1.0, $cohortAEntry['retention'][0], 0.01);
    }

    public function test_audit_fires_alert_when_churn_above_threshold(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        Subscription::factory()->active()->forPlan($plan)->create([
            'created_at' => $lastMonth->copy()->subMonth(),
        ]);
        Subscription::factory()->forPlan($plan)->create([
            'created_at' => $lastMonth->copy()->subMonth(),
            'status' => 'cancelled',
            'cancelled_at' => $lastMonth->copy()->addDays(5),
        ]);

        \Artisan::call('churn:audit', ['--threshold' => '0.1']);

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'high_churn_rate',
            'severity' => 'sev2',
        ]);
    }

    public function test_audit_resolves_alert_when_churn_below_threshold(): void
    {
        AlertFiring::create([
            'alert_key' => 'high_churn_rate',
            'severity' => 'sev2',
            'value' => 0.5,
            'threshold' => 0.05,
            'fired_at' => now()->subHour(),
        ]);

        \Artisan::call('churn:audit');

        $firing = AlertFiring::where('alert_key', 'high_churn_rate')->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_audit_handles_zero_active_subscriptions(): void
    {
        $exit = \Artisan::call('churn:audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Monthly churn rate', $output);
    }
}
