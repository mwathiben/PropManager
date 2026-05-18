<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-56 GROWTH-ATTRIB-1 surface watchdog. Cross-category presence
 * map for every closure; per-category behavioural tests live in the
 * sibling Phase56* files.
 */
class Phase56GrowthAttribSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- MULTI-TOUCH ------------------------------------------------------

    public function test_attribution_touchpoints_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('attribution_touchpoints'));
        foreach (['user_id', 'channel', 'medium', 'campaign', 'landlord_id', 'touched_at'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('attribution_touchpoints', $col),
                "attribution_touchpoints missing column {$col}",
            );
        }
    }

    public function test_attribution_model_service_exists_with_compute_for_user(): void
    {
        $this->assertTrue(class_exists(\App\Services\Growth\AttributionModelService::class));
        $this->assertTrue(method_exists(\App\Services\Growth\AttributionModelService::class, 'computeForUser'));
        $this->assertCount(4, \App\Services\Growth\AttributionModelService::ALL_MODELS);
    }

    public function test_referral_attributed_listener_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Listeners\Growth\RecordReferralAttributedTouchpoint::class));
    }

    // -- FUNNEL-SANKEY ----------------------------------------------------

    public function test_funnel_stage_enum_has_four_cases(): void
    {
        $cases = \App\Services\Growth\FunnelStage::cases();
        $this->assertCount(4, $cases);
        foreach (['signup', 'onboarding_complete', 'first_payment', 'retained_60d'] as $value) {
            $this->assertNotNull(\App\Services\Growth\FunnelStage::tryFrom($value));
        }
    }

    public function test_funnel_event_emitter_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\Growth\FunnelEventEmitter::class));
        $this->assertTrue(method_exists(\App\Services\Growth\FunnelEventEmitter::class, 'emit'));
    }

    public function test_funnel_sankey_vue_component_exists(): void
    {
        $this->assertFileExists(base_path('resources/js/Components/Growth/FunnelSankey.vue'));
    }

    // -- COHORT-BY-SOURCE -------------------------------------------------

    public function test_users_acquisition_source_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'acquisition_source'));
    }

    public function test_churn_service_has_cohorts_by_source_method(): void
    {
        $this->assertTrue(method_exists(\App\Services\Growth\ChurnService::class, 'cohortsBySource'));
    }

    // -- AB-AUTO-PROMOTE --------------------------------------------------

    public function test_auto_promote_command_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Console\Commands\ExperimentsAutoPromote::class));
        $this->assertSame('experiments:auto-promote', (new \App\Console\Commands\ExperimentsAutoPromote)->getName());
    }

    public function test_auto_promote_scheduled_daily_at_0330(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'experiments:auto-promote'));

        $this->assertNotNull($entry, 'experiments:auto-promote schedule entry missing.');
        $this->assertSame('30 3 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_experiment_concluded_event_and_listener_wired(): void
    {
        $this->assertTrue(class_exists(\App\Events\ExperimentConcluded::class));
        $this->assertTrue(class_exists(\App\Listeners\Growth\LogExperimentConclusion::class));
        $this->assertTrue(method_exists(\App\Listeners\Growth\LogExperimentConclusion::class, 'handle'));
    }

    public function test_experiments_success_event_name_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('experiments', 'success_event_name'));
    }

    // -- DASHBOARDS -------------------------------------------------------

    public function test_ops_growth_attribution_controller_and_route_registered(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Ops\OpsGrowthAttributionController::class));
        $this->assertTrue(Route::has('ops.growth.attribution.index'));
    }

    public function test_attribution_vue_page_exists(): void
    {
        $this->assertFileExists(base_path('resources/js/Pages/Ops/Growth/Attribution.vue'));
    }

    // -- CI ---------------------------------------------------------------

    public function test_growth_runbook_mentions_phase_56(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/growth.md'));
        $this->assertStringContainsString('Phase 56', $body);
        $this->assertStringContainsString('GROWTH-ATTRIB-1', $body);
    }
}
