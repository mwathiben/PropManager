<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-66 COHORT-RETENTION-3 surface watchdog: route + controller +
 * service + Vue component + config + i18n copy are all present, so
 * drift (a deleted component, a renamed route) fails CI even though
 * PHPUnit can't render the heatmap.
 */
class Phase66CohortRetentionSurfaceTest extends TestCase
{
    private function read(string $relative): string
    {
        $path = base_path($relative);
        $this->assertFileExists($path, "{$relative} should exist");

        return (string) file_get_contents($path);
    }

    public function test_ops_route_is_registered(): void
    {
        $this->assertTrue(Route::has('ops.growth.cohort-retention.index'));
    }

    public function test_backend_files_and_config_exist(): void
    {
        $this->read('app/Services/Growth/CohortRetentionService.php');
        $this->read('app/Http/Controllers/Ops/OpsCohortRetentionController.php');

        $growth = require base_path('config/growth.php');
        $this->assertArrayHasKey('cohort', $growth);
        $this->assertArrayHasKey('min_sample', $growth['cohort']);
    }

    public function test_component_and_copy_present(): void
    {
        $vue = $this->read('resources/js/Pages/Ops/Growth/CohortRetention.vue');
        $this->assertStringContainsString('source_comparison', $vue);
        $this->assertStringContainsString('delta_vs_organic', $vue);

        foreach (['en', 'sw'] as $locale) {
            $growth = require base_path("lang/{$locale}/growth.php");
            $this->assertArrayHasKey('cohort', $growth, "[{$locale}] missing cohort copy");
            $this->assertArrayHasKey('sources', $growth['cohort']);
        }
    }
}
