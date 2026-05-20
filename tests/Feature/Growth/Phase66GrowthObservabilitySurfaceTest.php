<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase-66 GROWTH-OBSERVABILITY-3 surface watchdog: both roll-up
 * commands are registered, the NPS service + command files exist, the
 * alert is in the registry, and the Attribution dashboard carries the
 * NPS card + cross-links.
 */
class Phase66GrowthObservabilitySurfaceTest extends TestCase
{
    private function read(string $relative): string
    {
        $path = base_path($relative);
        $this->assertFileExists($path, "{$relative} should exist");

        return (string) file_get_contents($path);
    }

    public function test_rollup_commands_are_registered(): void
    {
        $commands = Artisan::all();
        $this->assertArrayHasKey('nps:rollup', $commands);
        $this->assertArrayHasKey('growth:leaderboard-rollup', $commands);
    }

    public function test_backend_files_and_alert_present(): void
    {
        $this->read('app/Services/Growth/NpsScoreService.php');
        $this->read('app/Console/Commands/NpsRollup.php');
        $this->read('app/Console/Commands/GrowthLeaderboardRollup.php');

        $alerts = require base_path('config/alerts.php');
        $keys = array_column($alerts['alerts'], 'key');
        $this->assertContains('nps_negative', $keys);
    }

    public function test_attribution_dashboard_has_nps_card_and_links(): void
    {
        $vue = $this->read('resources/js/Pages/Ops/Growth/Attribution.vue');
        $this->assertStringContainsString('data-testid="nps-card"', $vue);
        $this->assertStringContainsString("route('ops.growth.cohort-retention.index')", $vue);
        $this->assertStringContainsString("route('ops.growth.referral-leaderboard.index')", $vue);

        $controller = $this->read('app/Http/Controllers/Ops/OpsGrowthAttributionController.php');
        $this->assertStringContainsString("'nps' =>", $controller);
    }
}
