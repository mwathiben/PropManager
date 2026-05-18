<?php

declare(strict_types=1);

namespace Tests\Feature\Cleanup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-53 DEFER-CLEANUP-3 surface watchdog.
 *
 * Per-finding invariants asserted so a future refactor cannot
 * silently regress any of the 18 Phase-53 closures. Lives alongside
 * Phase38CleanupSurfaceTest as the second defer-cleanup cycle anchor.
 *
 * Invariants:
 *  GAUGE-WIRING-1 — tenant-kyc:blocked-audit cron registered.
 *  GAUGE-WIRING-2 — report_render_failure_count surface labels wired.
 *  GAUGE-WIRING-3 — i18n:spend-audit cron registered.
 *  VUE-TELEMETRY-1 — POST /api/telemetry/vue-preview-poll-pause exists.
 *  TEST-DEBT-1 — Phase29CiTest no longer uses WorkflowRunLog::firstOrFail().
 *  TEST-DEBT-3 — Phase38CleanupSurfaceTest baseline at or below 98.
 *  RTL-BASELINES — directory + runbook + CI wiring exist.
 *  ESLINT-RATCHET — baseline file + scripts/lint-baseline.mjs + npm script exist.
 *  CI-2 — cleanup.md mentions Phase 53.
 */
class Phase53DeferCleanup3SurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_kyc_blocked_audit_is_scheduled(): void
    {
        $events = collect(\Illuminate\Support\Facades\Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'tenant-kyc:blocked-audit'));

        $this->assertNotNull($entry, 'tenant-kyc:blocked-audit is not scheduled.');
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_i18n_spend_audit_is_scheduled(): void
    {
        $events = collect(\Illuminate\Support\Facades\Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'i18n:spend-audit'));

        $this->assertNotNull($entry, 'i18n:spend-audit is not scheduled.');
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_report_render_failure_surfaces_have_labels(): void
    {
        // BuilderController uses a helper bumpReportRenderFailure(label)
        // so the label appears as a string-literal argument rather than
        // inline. The other three surfaces inline 'surface' => 'X'. Both
        // patterns count.
        $files = [
            'app/Http/Controllers/Reports/BuilderController.php' => 'builder',
            'app/Services/Reports/DashboardService.php' => 'dashboard',
            'app/Http/Controllers/Reports/ScheduledController.php' => 'scheduled',
            'app/Console/Commands/SendScheduledReports.php' => 'scheduled_send',
        ];

        foreach ($files as $path => $expectedSurfaceLabel) {
            $body = (string) file_get_contents(base_path($path));
            $this->assertStringContainsString(
                'report_render_failure_count',
                $body,
                "Surface {$path} does not increment report_render_failure_count.",
            );
            $inline = "'surface' => '{$expectedSurfaceLabel}'";
            $helperArg = "('{$expectedSurfaceLabel}')";
            $this->assertTrue(
                str_contains($body, $inline) || str_contains($body, $helperArg),
                "Surface {$path} missing the {$expectedSurfaceLabel} label (neither '{$inline}' nor '{$helperArg}' present).",
            );
        }
    }

    public function test_telemetry_endpoint_route_exists(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('telemetry.vue-preview-poll-pause'),
            'POST /api/telemetry/vue-preview-poll-pause route not registered.',
        );
    }

    public function test_phase29_ci_test_no_longer_uses_firstorfail(): void
    {
        $body = (string) file_get_contents(base_path('tests/Feature/Workflow/Phase29CiTest.php'));
        $this->assertStringNotContainsString(
            'WorkflowRunLog::firstOrFail()',
            $body,
            'Phase29CiTest still uses WorkflowRunLog::firstOrFail() — the firstOrFail pollution fix did not land.',
        );
        $this->assertStringContainsString(
            "where('workflow_name', 'WF-RENT-REMIND-1')",
            $body,
            'Phase29CiTest no longer scopes by workflow_name; the firstOrFail replacement is missing.',
        );
    }

    public function test_test_error_baseline_at_or_below_98(): void
    {
        $body = (string) file_get_contents(base_path('tests/Feature/Cleanup/Phase38CleanupSurfaceTest.php'));
        $match = preg_match('/\$baseline = (\d+);/', $body, $matches);
        $this->assertSame(1, $match, 'Could not parse $baseline value out of Phase38CleanupSurfaceTest.');
        $this->assertLessThanOrEqual(98, (int) $matches[1], 'Phase-38 test-error baseline must be 98 or lower after Phase 53.');
    }

    public function test_rtl_baseline_directory_pinned(): void
    {
        $this->assertFileExists(base_path('tests/a11y/rtl/__screenshots__/.gitkeep'));
    }

    public function test_rtl_snapshots_runbook_exists(): void
    {
        $path = base_path('docs/runbooks/rtl-snapshots.md');
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('npm run test:rtl:update', $body);
        $this->assertStringContainsString('maxDiffPixelRatio', $body);
    }

    public function test_rtl_smoke_ci_job_wired(): void
    {
        $body = (string) file_get_contents(base_path('.github/workflows/ci.yml'));
        $this->assertStringContainsString('rtl-smoke:', $body);
        $this->assertStringContainsString('npm run test:rtl', $body);
    }

    public function test_eslint_baseline_file_present(): void
    {
        $path = base_path('.eslint-baseline.json');
        $this->assertFileExists($path);
        $payload = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('rules', $payload);
        $this->assertArrayHasKey('propmanager/no-hardcoded-english-strings', $payload['rules']);
        $this->assertArrayHasKey('propmanager/no-ltr-class', $payload['rules']);
    }

    public function test_lint_baseline_script_present(): void
    {
        $this->assertFileExists(base_path('scripts/lint-baseline.mjs'));
        $pkg = json_decode((string) file_get_contents(base_path('package.json')), true);
        $this->assertArrayHasKey('lint:baseline', $pkg['scripts'] ?? []);
    }

    public function test_cleanup_runbook_mentions_phase_53(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/cleanup.md'));
        $this->assertStringContainsString('Phase 53', $body);
        $this->assertStringContainsString('DEFER-CLEANUP-3', $body);
    }
}
