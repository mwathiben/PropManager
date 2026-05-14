<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase-21 Phase-3 coverage (LOW + operator-facing):
 *   DEFER-OBSERV-3: slow-query:report aggregation command.
 *   DEFER-PERF-1: PERF-5 SoftDeletes-index non-adoption decision doc.
 *   DEFER-PERF-2: TenantClock per-callsite migration (4 controllers).
 *   DEFER-SUPPLY-1: CycloneDX SBOM emission in CI.
 */
class Phase21Phase3Test extends TestCase
{
    use RefreshDatabase;

    public function test_slow_query_report_command_handles_missing_logs_gracefully(): void
    {
        // DEFER-OBSERV-3: SlowQueryServiceProvider is a no-op unless
        // SLOW_QUERY_THRESHOLD_MS is set, so most environments have no
        // slow-query-*.log files. The report must exit SUCCESS with an
        // informative message, not crash.
        $exitCode = Artisan::call('slow-query:report', ['--since' => '60d', '--top' => 20]);

        $this->assertSame(0, $exitCode, 'slow-query:report with no log files = graceful SUCCESS.');
    }

    public function test_slow_query_report_command_is_registered(): void
    {
        $this->assertArrayHasKey(
            'slow-query:report',
            Artisan::all(),
            'DEFER-OBSERV-3: slow-query:report must be a registered artisan command.',
        );
    }

    public function test_perf_5_non_adoption_decision_is_documented(): void
    {
        // DEFER-PERF-1: PERF-5 was deferred pending slow-query evidence.
        // Phase-21 ships the decision either way — the doc must exist
        // and capture the evidence path + revisit triggers.
        $path = base_path('docs/runbooks/perf-5-non-adoption.md');
        $this->assertFileExists($path, 'DEFER-PERF-1: the PERF-5 decision must be documented.');

        $contents = file_get_contents($path);
        $this->assertStringContainsString('slow-query:report', $contents, 'Decision doc must reference the evidence-collection command.');
        $this->assertStringContainsString('Revisit Triggers', $contents, 'Decision doc must define when to re-open the decision.');
        $this->assertStringContainsString('is_active', $contents, 'Decision doc must name the candidate workaround.');
    }

    public function test_perf2_controllers_import_tenant_clock(): void
    {
        // DEFER-PERF-2: the 4 migrated controllers must import TenantClock.
        // Pre-migration they used Carbon::today() / Carbon::now() against
        // APP_TIMEZONE — a non-Kenya user saw the server's day boundary.
        $migratedControllers = [
            'app/Http/Controllers/ActivityLogController.php',
            'app/Http/Controllers/ArchiveHubController.php',
            'app/Http/Controllers/ArrearsController.php',
            'app/Http/Controllers/Api/ReportController.php',
        ];

        foreach ($migratedControllers as $relative) {
            $source = file_get_contents(base_path($relative));
            $this->assertStringContainsString(
                'use App\\Support\\TenantClock;',
                $source,
                "DEFER-PERF-2: $relative must import TenantClock.",
            );
            $this->assertStringContainsString(
                'TenantClock::nowFor(',
                $source,
                "DEFER-PERF-2: $relative must call TenantClock::nowFor() for user-TZ-anchored date math.",
            );
        }
    }

    public function test_ci_workflow_emits_cyclonedx_sbom(): void
    {
        // DEFER-SUPPLY-1: CycloneDX format alongside the raw inventory.
        $ci = file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertStringContainsString(
            'cyclonedx/cyclonedx-php-composer',
            $ci,
            'DEFER-SUPPLY-1: CI sbom job must generate CycloneDX for composer dependencies.',
        );
        $this->assertStringContainsString(
            '@cyclonedx/cyclonedx-npm',
            $ci,
            'DEFER-SUPPLY-1: CI sbom job must generate CycloneDX for npm dependencies.',
        );
        $this->assertStringContainsString(
            'sbom-composer.cyclonedx.json',
            $ci,
            'DEFER-SUPPLY-1: CycloneDX composer SBOM must be in the uploaded artifact set.',
        );
        $this->assertStringContainsString(
            'sbom-npm.cyclonedx.json',
            $ci,
            'DEFER-SUPPLY-1: CycloneDX npm SBOM must be in the uploaded artifact set.',
        );
    }
}
