<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\MrrSnapshot;
use App\Models\Subscription;
use App\Models\SubscriptionChange;
use App\Models\SubscriptionPlan;
use App\Services\Growth\MrrSnapshotService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase35BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_plan_writes_audit_row_with_immediate_effective_at(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->monthly()->forPlan($starter)->create();

        app(SubscriptionService::class)->changePlan($sub, $pro);

        $change = SubscriptionChange::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($change);
        $this->assertSame('upgrade', $change->change_type);
        $this->assertSame($starter->id, $change->from_plan_id);
        $this->assertSame($pro->id, $change->to_plan_id);
        $this->assertNotNull($change->effective_at);
        $this->assertNull($change->scheduled_for);
    }

    public function test_change_plan_classifies_downgrade(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->forPlan($pro)->create();

        app(SubscriptionService::class)->changePlan($sub, $starter);

        $change = SubscriptionChange::where('subscription_id', $sub->id)->first();
        $this->assertSame('downgrade', $change->change_type);
    }

    public function test_change_plan_computes_prorated_amount_for_upgrade(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->monthly()->forPlan($starter)->create([
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        app(SubscriptionService::class)->changePlan($sub, $pro);

        $change = SubscriptionChange::where('subscription_id', $sub->id)->first();
        // Delta = 5000 - 1500 = 3500. Remaining = 20 of 30. Prorated ≈ 2333.33
        $this->assertGreaterThan(2000, (float) $change->prorated_amount_kes);
        $this->assertLessThan(2500, (float) $change->prorated_amount_kes);
    }

    public function test_change_plan_skips_proration_for_downgrade(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->forPlan($pro)->create();

        app(SubscriptionService::class)->changePlan($sub, $starter);

        $change = SubscriptionChange::where('subscription_id', $sub->id)->first();
        $this->assertSame(0.0, (float) $change->prorated_amount_kes);
    }

    public function test_schedule_downgrade_at_period_end_does_not_apply_immediately(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->forPlan($pro)->create([
            'current_period_end' => now()->addDays(10),
        ]);

        app(SubscriptionService::class)->scheduleDowngradeAtPeriodEnd($sub, $starter);

        $change = SubscriptionChange::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($change->scheduled_for);
        $this->assertNull($change->effective_at);
        $sub->refresh();
        $this->assertSame($pro->id, $sub->plan_id); // Plan NOT yet changed.
    }

    public function test_apply_downgrades_cron_applies_due_changes(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->forPlan($pro)->create();
        SubscriptionChange::create([
            'subscription_id' => $sub->id,
            'from_plan_id' => $pro->id,
            'to_plan_id' => $starter->id,
            'change_type' => 'downgrade',
            'prorated_amount_kes' => 0,
            'scheduled_for' => now()->subHour(),
            'effective_at' => null,
        ]);

        \Artisan::call('subscriptions:apply-downgrades');

        $sub->refresh();
        $this->assertSame($starter->id, $sub->plan_id);
        $change = SubscriptionChange::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($change->effective_at);
    }

    public function test_apply_downgrades_skips_future_scheduled(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->forPlan($pro)->create();
        SubscriptionChange::create([
            'subscription_id' => $sub->id,
            'from_plan_id' => $pro->id,
            'to_plan_id' => $starter->id,
            'change_type' => 'downgrade',
            'prorated_amount_kes' => 0,
            'scheduled_for' => now()->addDays(5),
            'effective_at' => null,
        ]);

        \Artisan::call('subscriptions:apply-downgrades');

        $sub->refresh();
        $this->assertSame($pro->id, $sub->plan_id); // Still on pro.
    }

    public function test_mrr_snapshot_populates_expansion_from_subscription_changes(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->monthly()->forPlan($starter)->create();
        // Existing sub on starter; upgrade to pro today.
        app(SubscriptionService::class)->changePlan($sub, $pro);

        app(MrrSnapshotService::class)->snapshotForDate();

        $proRow = MrrSnapshot::where('plan_id', $pro->id)->first();
        // Expansion = 5000 - 1500 = 3500.
        $this->assertEqualsWithDelta(3500.00, (float) $proRow->expansion_mrr_kes, 0.01);
    }

    public function test_mrr_snapshot_populates_contraction_from_subscription_changes(): void
    {
        $starter = SubscriptionPlan::factory()->starter()->create();
        $pro = SubscriptionPlan::factory()->professional()->create();
        $sub = Subscription::factory()->active()->monthly()->forPlan($pro)->create();
        app(SubscriptionService::class)->changePlan($sub, $starter);

        app(MrrSnapshotService::class)->snapshotForDate();

        $proRow = MrrSnapshot::where('plan_id', $pro->id)->first();
        // Contraction = 5000 - 1500 = 3500 (pro lost it).
        $this->assertEqualsWithDelta(3500.00, (float) $proRow->contraction_mrr_kes, 0.01);
    }
}
