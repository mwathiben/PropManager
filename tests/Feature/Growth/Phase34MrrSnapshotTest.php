<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\MrrSnapshot;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Growth\MrrSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase34MrrSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_creates_one_row_per_plan(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $professional = SubscriptionPlan::factory()->professional()->create();

        Subscription::factory()->active()->monthly()->forPlan($starter)->count(2)->create();
        Subscription::factory()->active()->monthly()->forPlan($professional)->count(1)->create();

        app(MrrSnapshotService::class)->snapshotForDate();

        $this->assertSame(2, MrrSnapshot::count());
        $starterRow = MrrSnapshot::where('plan_id', $starter->id)->first();
        $proRow = MrrSnapshot::where('plan_id', $professional->id)->first();
        $this->assertEqualsWithDelta(3000.00, (float) $starterRow->mrr_kes, 0.01);
        $this->assertSame(2, $starterRow->active_subscriptions);
        $this->assertEqualsWithDelta(5000.00, (float) $proRow->mrr_kes, 0.01);
    }

    public function test_yearly_subscription_contributes_one_twelfth(): void
    {
        $plan = SubscriptionPlan::factory()->professional()->create();
        Subscription::factory()->active()->yearly()->forPlan($plan)->create();

        app(MrrSnapshotService::class)->snapshotForDate();

        $row = MrrSnapshot::where('plan_id', $plan->id)->first();
        $this->assertEqualsWithDelta(50000.0 / 12.0, (float) $row->mrr_kes, 0.01);
    }

    public function test_trialing_subscription_does_not_count_toward_mrr(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        Subscription::factory()->trialing()->forPlan($plan)->create();

        app(MrrSnapshotService::class)->snapshotForDate();

        $row = MrrSnapshot::where('plan_id', $plan->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $row->mrr_kes, 0.01);
        $this->assertSame(0, $row->active_subscriptions);
    }

    public function test_cancelled_subscription_drops_off_after_cancel_date(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $sub = Subscription::factory()->active()->monthly()->forPlan($plan)->create([
            'created_at' => now()->subDays(30),
        ]);
        $sub->update(['status' => 'cancelled', 'cancelled_at' => now()->subDay()]);

        app(MrrSnapshotService::class)->snapshotForDate();

        $row = MrrSnapshot::where('plan_id', $plan->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $row->mrr_kes, 0.01);
    }

    public function test_snapshot_is_idempotent_for_same_day(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        Subscription::factory()->active()->monthly()->forPlan($plan)->create();

        app(MrrSnapshotService::class)->snapshotForDate();
        app(MrrSnapshotService::class)->snapshotForDate();

        $this->assertSame(1, MrrSnapshot::count());
    }

    public function test_new_mrr_isolates_subscriptions_created_today(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        Subscription::factory()->active()->monthly()->forPlan($plan)->create([
            'created_at' => now()->subDays(10),
        ]);
        Subscription::factory()->active()->monthly()->forPlan($plan)->create([
            'created_at' => now(),
        ]);

        app(MrrSnapshotService::class)->snapshotForDate();
        $row = MrrSnapshot::where('plan_id', $plan->id)->first();

        $this->assertEqualsWithDelta(3000.00, (float) $row->mrr_kes, 0.01);
        $this->assertEqualsWithDelta(1500.00, (float) $row->new_mrr_kes, 0.01);
        $this->assertSame(2, $row->active_subscriptions);
    }

    public function test_artisan_command_runs_and_emits_total(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        Subscription::factory()->active()->monthly()->forPlan($plan)->create();

        $exit = \Artisan::call('mrr:snapshot');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Total MRR', $output);
    }

    public function test_trend_endpoint_returns_super_admin_only(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($landlord)
            ->getJson(route('ops.mrr.trend'))
            ->assertForbidden();
    }

    public function test_trend_endpoint_aggregates_days(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'super_admin';
        $admin->save();

        $plan = SubscriptionPlan::factory()->starter()->create();
        MrrSnapshot::create([
            'day' => Carbon::today()->subDays(2)->toDateString(),
            'plan_id' => $plan->id,
            'mrr_kes' => 1500,
            'active_subscriptions' => 1,
        ]);
        MrrSnapshot::create([
            'day' => Carbon::today()->subDay()->toDateString(),
            'plan_id' => $plan->id,
            'mrr_kes' => 3000,
            'active_subscriptions' => 2,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('ops.mrr.trend', ['days' => 7]))
            ->assertOk()
            ->json();

        $this->assertSame(7, $response['window_days']);
        $this->assertCount(2, $response['days']);
        $this->assertEqualsWithDelta(3000.0, $response['days'][1]['mrr_kes_total'], 0.01);
    }
}
