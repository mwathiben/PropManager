<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-66 CI-1: cross-category presence map for the GROWTH-DEPTH-2
 * cycle. One watchdog asserting every load-bearing artifact across all
 * six categories (NPS / referral leaderboard / onboarding tour / cohort
 * retention / observability) still exists and is wired — so a refactor
 * that deletes a table, route, class, command, or Vue file fails CI even
 * where the behavioural tests don't reach.
 */
class Phase66GrowthDepth2SurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('nps_responses'));
        $this->assertTrue(Schema::hasTable('nps_prompt_states'));
        $this->assertTrue(Schema::hasTable('user_tour_states'));
        $this->assertTrue(Schema::hasColumn('users', 'leaderboard_opt_out'));

        $this->assertTrue(Schema::hasColumn('nps_responses', 'category'));
        $this->assertTrue(Schema::hasColumn('user_tour_states', 'status'));
    }

    public function test_backend_classes_exist(): void
    {
        $classes = [
            // NPS
            \App\Models\NpsResponse::class,
            \App\Http\Controllers\NpsResponseController::class,
            \App\Policies\NpsResponsePolicy::class,
            \App\Services\Growth\NpsEligibilityService::class,
            \App\Services\Growth\NpsScoreService::class,
            // Referral leaderboard
            \App\Services\Growth\ReferralLeaderboardService::class,
            \App\Http\Controllers\Growth\ReferralLeaderboardController::class,
            \App\Http\Controllers\Ops\OpsReferralLeaderboardController::class,
            \App\Http\Controllers\Growth\LeaderboardOptOutController::class,
            // Onboarding tour
            \App\Models\UserTourState::class,
            \App\Services\Onboarding\TourService::class,
            \App\Http\Controllers\Onboarding\OnboardingTourController::class,
            // Cohort retention
            \App\Services\Growth\CohortRetentionService::class,
            \App\Http\Controllers\Ops\OpsCohortRetentionController::class,
            // Observability
            \App\Console\Commands\NpsRollup::class,
            \App\Console\Commands\GrowthLeaderboardRollup::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), "Missing class {$class}");
        }
    }

    public function test_routes_are_registered(): void
    {
        $routes = [
            'nps.store', 'nps.impression', 'nps.dismiss', 'nps.opt-out',
            'growth.leaderboard', 'growth.leaderboard.opt-out',
            'ops.growth.referral-leaderboard.index',
            'onboarding-tour.advance', 'onboarding-tour.complete', 'onboarding-tour.dismiss',
            'ops.growth.cohort-retention.index',
        ];

        foreach ($routes as $name) {
            $this->assertTrue(Route::has($name), "Missing route {$name}");
        }
    }

    public function test_vue_components_exist(): void
    {
        $files = [
            'resources/js/Components/Nps/NpsSurveyModal.vue',
            'resources/js/Pages/Growth/Leaderboard.vue',
            'resources/js/Pages/Ops/Growth/ReferralLeaderboard.vue',
            'resources/js/Components/Tour/TourOverlay.vue',
            'resources/js/Components/Tour/TourTooltip.vue',
            'resources/js/Pages/Ops/Growth/CohortRetention.vue',
        ];

        foreach ($files as $relative) {
            $this->assertFileExists(base_path($relative));
        }
    }

    public function test_dependency_config_and_commands(): void
    {
        $pkg = json_decode((string) file_get_contents(base_path('package.json')), true);
        $this->assertArrayHasKey('@floating-ui/vue', $pkg['dependencies'] ?? []);

        $this->assertIsArray(config('nps'));
        $this->assertNotNull(config('referral.leaderboard.max'));
        $this->assertNotNull(config('growth.cohort.min_sample'));

        $commands = Artisan::all();
        $this->assertArrayHasKey('nps:rollup', $commands);
        $this->assertArrayHasKey('growth:leaderboard-rollup', $commands);
    }

    public function test_per_category_feature_tests_present(): void
    {
        $tests = [
            'tests/Feature/Growth/Phase66NpsSurveyTest.php',
            'tests/Feature/Growth/Phase66ReferralLeaderboardTest.php',
            'tests/Feature/Onboarding/Phase66OnboardingTourTest.php',
            'tests/Feature/Growth/Phase66CohortRetentionServiceTest.php',
            'tests/Feature/Growth/Phase66GrowthObservabilityTest.php',
        ];

        foreach ($tests as $relative) {
            $this->assertFileExists(base_path($relative));
        }
    }
}
