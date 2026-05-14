<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Support\NPlusOneBaseline;
use Tests\TestCase;

/**
 * Phase-22 PERF-SCALE-2 + PERF-NPLUS1-2 watchdog.
 *
 * PERF-SCALE-2: the maintenance page must be a self-contained HTML
 * document (no Vite/Inertia — the asset pipeline may be mid-deploy),
 * and the graceful-shutdown contract must be documented.
 *
 * PERF-NPLUS1-2: the N+1 baseline cleanup. The allow-list is empty
 * (the suite surfaced zero violations), so this finding verifies the
 * empty state and the documented convention rather than fixing
 * offenders.
 */
class Phase22ScaleTest extends TestCase
{
    public function test_maintenance_view_exists_and_is_self_contained(): void
    {
        $path = resource_path('views/errors/503.blade.php');
        $this->assertFileExists($path, 'PERF-SCALE-2: a custom 503 maintenance view must exist.');

        $source = file_get_contents($path);

        // Must render with zero asset-pipeline dependencies — maintenance
        // mode is exactly when Vite/Inertia may be unavailable.
        $this->assertStringNotContainsString('@vite', $source, 'PERF-SCALE-2: the 503 page must not depend on @vite.');
        $this->assertStringNotContainsString('@inertia', $source, 'PERF-SCALE-2: the 503 page must not depend on Inertia.');
        $this->assertStringContainsString('<!DOCTYPE html>', $source, 'PERF-SCALE-2: the 503 page must be a standalone HTML document.');
        $this->assertStringContainsString('maintenance', strtolower($source), 'PERF-SCALE-2: the 503 page should explain it is a maintenance window.');
    }

    public function test_maintenance_view_renders(): void
    {
        // The view must compile + render without a runtime error.
        $html = view('errors.503')->render();
        $this->assertStringContainsString('PropManager', $html);
        $this->assertStringContainsString("We'll be right back", $html);
    }

    public function test_graceful_shutdown_contract_is_documented(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/queue-worker-config.md'));

        $this->assertStringContainsString(
            'Graceful shutdown',
            $runbook,
            'PERF-SCALE-2: queue-worker-config.md must document the graceful-shutdown contract.',
        );
        $this->assertStringContainsString('SIGTERM', $runbook, 'PERF-SCALE-2: the SIGTERM behaviour must be documented.');
        $this->assertStringContainsString('stopwaitsecs', $runbook, 'PERF-SCALE-2: the stopwaitsecs > --timeout invariant must be documented.');
    }

    public function test_nplus1_baseline_is_empty_and_documented(): void
    {
        // PERF-NPLUS1-2: the cleanup target is an empty allow-list. The
        // suite surfaced zero lazy-load violations, so there is nothing
        // to fix — this verifies the goal state holds.
        $this->assertSame(
            [],
            NPlusOneBaseline::ALLOWED,
            'PERF-NPLUS1-2: the N+1 baseline allow-list must be empty — the cleanup goal state.',
        );

        $this->assertFileExists(
            base_path('docs/runbooks/n-plus-one.md'),
            'PERF-NPLUS1-2: the N+1 prevention convention must be documented.',
        );
        $doc = file_get_contents(base_path('docs/runbooks/n-plus-one.md'));
        $this->assertStringContainsString('preventLazyLoading', $doc);
        $this->assertStringContainsString('NPlusOneBaseline', $doc);
    }
}
