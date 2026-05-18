<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\AttributionTouchpoint;
use App\Models\Experiment;
use App\Models\ProductEvent;
use App\Models\User;
use App\Services\Growth\FunnelStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-56 DASHBOARDS-1/2/3 watchdog. Verifies super_admin auth gate
 * + Inertia payload shape + cross-role denial.
 */
class Phase56OpsAttributionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_dashboard_with_four_payload_keys(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create(['acquisition_source' => 'organic']);
        AttributionTouchpoint::create([
            'user_id' => $user->id,
            'channel' => 'organic_search',
            'touched_at' => now()->subDays(5),
        ]);
        ProductEvent::create([
            'user_id' => $user->id,
            'landlord_id' => $user->id,
            'event_name' => FunnelStage::SIGNUP->eventName(),
            'created_at' => now()->subDays(5),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('ops.growth.attribution.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ops/Growth/Attribution')
                ->has('attribution_summary')
                ->has('funnel_sankey')
                ->has('cohort_by_source')
                ->has('experiments_auto_promoted'));
    }

    public function test_landlord_role_denied(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)->get(route('ops.growth.attribution.index'));

        $this->assertContains(
            $response->status(),
            [403, 404],
            'Non-super_admin must be blocked from /ops/growth/attribution.',
        );
    }

    public function test_attribution_summary_aggregates_recent_touchpoints(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create();
        // Two touches in the 30d window for the same user.
        AttributionTouchpoint::create([
            'user_id' => $user->id,
            'channel' => 'organic_search',
            'touched_at' => now()->subDays(20),
        ]);
        AttributionTouchpoint::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'touched_at' => now()->subDays(5),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('ops.growth.attribution.index'))
            ->assertInertia(fn ($page) => $page
                ->has('attribution_summary.first_touch.0.channel')
                ->where('attribution_summary.first_touch.0.channel', 'organic_search')
                ->has('attribution_summary.last_touch.0.channel')
                ->where('attribution_summary.last_touch.0.channel', 'email'));
    }

    public function test_concluded_experiment_appears_in_auto_promoted_timeline(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        Experiment::create([
            'experiment_key' => 'phase56-test',
            'name' => 'Phase 56 test',
            'status' => Experiment::STATUS_CONCLUDED,
            'variants' => [['key' => 'control'], ['key' => 'variant_b']],
            'winning_variant_key' => 'variant_b',
            'ends_at' => now()->subHour(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('ops.growth.attribution.index'))
            ->assertInertia(fn ($page) => $page
                ->has('experiments_auto_promoted.0')
                ->where('experiments_auto_promoted.0.experiment_key', 'phase56-test')
                ->where('experiments_auto_promoted.0.winning_variant_key', 'variant_b'));
    }
}
