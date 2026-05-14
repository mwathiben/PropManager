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

    public function test_no_v_html_paginator_labels_remain(): void
    {
        // DEFER-FRONT-1 (closes Phase-15 FRONT-4 deferral): every
        // paginator label must render via <PaginatorLink>, not
        // v-html="link.label". v-html on framework-controlled HTML is
        // low-risk but desensitises maintainers to v-html safety — the
        // watchdog keeps the count at 0.
        $pagesPath = base_path('resources/js/Pages');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pagesPath, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $offenders = [];
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'vue') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (preg_match('/v-html\s*=\s*"link\.label"/', $contents)) {
                $offenders[] = str_replace($pagesPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'DEFER-FRONT-1: v-html="link.label" must be fully replaced by <PaginatorLink>. Offenders: '.implode(', ', $offenders),
        );
    }

    public function test_tenant_notifications_use_optimistic_mark_as_read(): void
    {
        // DEFER-FRONT-4 (closes Phase-20 FRONT-UX-4 deferral): mark-as-read
        // + mark-all-as-read must update local state optimistically and
        // revert on error — NOT router.reload() the whole page.
        $source = file_get_contents(base_path('resources/js/Pages/Tenant/Notifications.vue'));

        // markAllAsRead must use the optimistic snapshot + revert pattern.
        $this->assertStringContainsString(
            'Snapshot for revert-on-error',
            $source,
            'DEFER-FRONT-4: markAllAsRead must use an optimistic snapshot + revert pattern.',
        );
        // markAsRead must update local state BEFORE the request (true
        // optimistic), capturing the previous value for revert.
        $this->assertStringContainsString(
            'const previous = notification.read_at;',
            $source,
            'DEFER-FRONT-4: markAsRead must capture previous read_at for optimistic revert.',
        );
        // Isolate the markAllAsRead function body + assert it's
        // router.reload-free (the whole-page refresh it replaced).
        $start = (int) strpos($source, 'const markAllAsRead');
        $end = (int) strpos($source, 'const acceptInvitation');
        $markAllBody = substr($source, $start, $end - $start);
        $this->assertStringNotContainsString(
            'router.reload',
            $markAllBody,
            'DEFER-FRONT-4: markAllAsRead must no longer router.reload() — optimistic local update only.',
        );
    }

    public function test_auth_forms_use_form_submit_button(): void
    {
        // DEFER-FRONT-3 (closes Phase-19 FRONT-UX-3 deferral): the 5 auth
        // forms must submit via <FormSubmitButton> (which centralises the
        // :processing spinner + disabled-while-submitting contract), not a
        // bare <PrimaryButton> that each page wired :disabled by hand.
        $authForms = [
            'resources/js/Pages/Auth/Login.vue',
            'resources/js/Pages/Auth/Register.vue',
            'resources/js/Pages/Auth/ForgotPassword.vue',
            'resources/js/Pages/Auth/ResetPassword.vue',
            'resources/js/Pages/Auth/ConfirmPassword.vue',
        ];

        foreach ($authForms as $relative) {
            $source = file_get_contents(base_path($relative));
            $this->assertStringContainsString(
                "import FormSubmitButton from '@/Components/FormSubmitButton.vue';",
                $source,
                "DEFER-FRONT-3: $relative must import FormSubmitButton.",
            );
            $this->assertStringContainsString(
                '<FormSubmitButton',
                $source,
                "DEFER-FRONT-3: $relative must submit via <FormSubmitButton>.",
            );
            $this->assertStringNotContainsString(
                "import PrimaryButton from '@/Components/PrimaryButton.vue';",
                $source,
                "DEFER-FRONT-3: $relative must no longer import the bare PrimaryButton for submit.",
            );
        }
    }

    public function test_icon_button_component_enforces_aria_label(): void
    {
        // DEFER-FRONT-5 (closes Phase-20 FRONT-UX-6 deferral): IconButton
        // centralises the icon-only button contract — ariaLabel is a
        // REQUIRED prop (icon-only buttons have no text), so a11y can no
        // longer be forgotten at the call site.
        $path = base_path('resources/js/Components/IconButton.vue');
        $this->assertFileExists($path, 'DEFER-FRONT-5: IconButton.vue must exist.');

        $source = file_get_contents($path);
        $this->assertStringContainsString(
            'ariaLabel: string;',
            $source,
            'DEFER-FRONT-5: ariaLabel must be a required (non-optional) prop.',
        );
        $this->assertStringContainsString(
            ':aria-label="ariaLabel"',
            $source,
            'DEFER-FRONT-5: IconButton must bind aria-label to the rendered element.',
        );
    }

    public function test_icon_button_is_adopted_across_call_sites(): void
    {
        // DEFER-FRONT-5: the migration must actually land — each migrated
        // page imports IconButton and renders it. Watchdog keeps adoption
        // from silently regressing back to bare <button> + icon.
        $adopters = [
            'resources/js/Pages/Tenants/Show.vue',
            'resources/js/Pages/MoveOuts/Show.vue',
            'resources/js/Pages/Settings/PayoutAccounts.vue',
        ];

        foreach ($adopters as $relative) {
            $source = file_get_contents(base_path($relative));
            $this->assertStringContainsString(
                "import IconButton from '@/Components/IconButton.vue';",
                $source,
                "DEFER-FRONT-5: $relative must import IconButton.",
            );
            $this->assertStringContainsString(
                '<IconButton',
                $source,
                "DEFER-FRONT-5: $relative must render <IconButton> at its icon-only action sites.",
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
