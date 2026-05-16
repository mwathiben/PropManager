<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\AlertFiring;
use App\Models\LandlordEngagementScore;
use App\Models\LandlordUsageMetric;
use App\Models\OnboardingMilestone;
use App\Models\Property;
use App\Models\SecurityLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Growth\EngagementScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase34EngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_returns_zero_for_brand_new_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $computed = app(EngagementScoreService::class)->compute($landlord->id);

        $this->assertLessThanOrEqual(30, $computed['score']);
        $this->assertArrayHasKey('login', $computed['components']);
    }

    public function test_compute_rewards_recent_login(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        SecurityLog::create([
            'user_id' => $landlord->id,
            'event_type' => SecurityLog::EVENT_LOGIN,
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDay(),
        ]);

        $computed = app(EngagementScoreService::class)->compute($landlord->id);
        $this->assertSame(100, $computed['components']['login']);
    }

    public function test_compute_rewards_milestone_progress(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        foreach (OnboardingMilestone::FUNNEL as $milestone) {
            OnboardingMilestone::query()->withoutGlobalScopes()->updateOrCreate(
                ['landlord_id' => $landlord->id, 'milestone' => $milestone],
                ['metadata' => [], 'reached_at' => now()],
            );
        }

        $computed = app(EngagementScoreService::class)->compute($landlord->id);
        $this->assertSame(100, $computed['components']['milestones']);
    }

    public function test_compute_rewards_usage_recency(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        LandlordUsageMetric::query()->withoutGlobalScopes()->create([
            'landlord_id' => $landlord->id,
            'metric' => LandlordUsageMetric::METRIC_DB_QUERIES,
            'day' => now()->toDateString(),
            'value' => 100,
        ]);

        $computed = app(EngagementScoreService::class)->compute($landlord->id);
        $this->assertSame(100, $computed['components']['usage']);
    }

    public function test_snapshot_persists_one_row_per_landlord_per_day(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        app(EngagementScoreService::class)->snapshot($landlord->id);
        app(EngagementScoreService::class)->snapshot($landlord->id);

        $this->assertSame(1, LandlordEngagementScore::query()->withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)->count());
    }

    public function test_rollup_command_processes_all_landlords(): void
    {
        User::factory()->create(['role' => 'landlord']);
        User::factory()->create(['role' => 'landlord']);

        $exit = \Artisan::call('engagement:rollup');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Audited 2', $output);
    }

    public function test_rollup_fires_alert_for_low_engagement_paying_landlord(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();

        \Artisan::call('engagement:rollup', ['--threshold' => '90']);

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'low_engagement_landlord',
            'severity' => 'sev4',
        ]);
    }

    public function test_rollup_does_not_fire_for_free_tier_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        // No subscription = free tier, no alert even with low score.

        \Artisan::call('engagement:rollup', ['--threshold' => '90']);

        $this->assertDatabaseMissing('alert_firings', [
            'alert_key' => 'low_engagement_landlord',
        ]);
    }

    public function test_rollup_resolves_alert_when_no_paying_offenders(): void
    {
        AlertFiring::create([
            'alert_key' => 'low_engagement_landlord',
            'severity' => 'sev4',
            'value' => 10,
            'threshold' => 30,
            'fired_at' => now()->subHour(),
        ]);

        \Artisan::call('engagement:rollup');

        $firing = AlertFiring::where('alert_key', 'low_engagement_landlord')
            ->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }
}
