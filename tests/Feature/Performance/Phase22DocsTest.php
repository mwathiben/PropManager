<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Tests\TestCase;

/**
 * Phase-22 PERF-LOAD-3 + PERF-SLO-4 + PERF-SCALE-3: operator-runbook
 * watchdogs. Each finding's deliverable is a doc; these tests pin that
 * the docs exist and cover the load-bearing sections, so a future
 * edit can't quietly gut them.
 */
class Phase22DocsTest extends TestCase
{
    public function test_load_testing_runbook_exists_and_is_complete(): void
    {
        $path = base_path('docs/runbooks/load-testing.md');
        $this->assertFileExists($path, 'PERF-LOAD-3: docs/runbooks/load-testing.md must exist.');

        $doc = file_get_contents($path);
        $this->assertStringContainsString('smoke.js', $doc, 'PERF-LOAD-3: must document the smoke script.');
        $this->assertStringContainsString('baseline.js', $doc, 'PERF-LOAD-3: must document the baseline script.');
        $this->assertStringContainsString('LoadTestSeeder', $doc, 'PERF-LOAD-3: must document seeding the load-test landlord.');
        $this->assertStringContainsString('re-baseline', $doc, 'PERF-LOAD-3: must define when to re-baseline.');
    }

    public function test_slo_doc_documents_route_classes_and_tooling(): void
    {
        $doc = file_get_contents(base_path('docs/runbooks/slo.md'));

        foreach (['read_path', 'write_path', 'webhook', 'report'] as $class) {
            $this->assertStringContainsString(
                $class,
                $doc,
                "PERF-SLO-4: slo.md must document the '{$class}' route class + its budget.",
            );
        }
        $this->assertStringContainsString('slo:report', $doc, 'PERF-SLO-4: slo.md must document the slo:report command.');
        $this->assertStringContainsString('http_request_ms', $doc, 'PERF-SLO-4: slo.md must document the http_request_ms metric.');
        $this->assertStringContainsString('config/observability.php', $doc, 'PERF-SLO-4: slo.md must point at the config as the source of truth.');
    }

    public function test_autoscale_runbook_exists_and_is_complete(): void
    {
        $path = base_path('docs/runbooks/autoscale-readiness.md');
        $this->assertFileExists($path, 'PERF-SCALE-3: docs/runbooks/autoscale-readiness.md must exist.');

        $doc = file_get_contents($path);
        $this->assertStringContainsString('SESSION_DRIVER', $doc, 'PERF-SCALE-3: must cover session externalisation.');
        $this->assertStringContainsString('CACHE_STORE', $doc, 'PERF-SCALE-3: must cover cache externalisation.');
        $this->assertStringContainsString('Scheduler', $doc, 'PERF-SCALE-3: must cover the single-scheduler-instance requirement.');
        $this->assertStringContainsString('checklist', strtolower($doc), 'PERF-SCALE-3: must include a horizontal-scale checklist.');
        $this->assertStringContainsString("Storage::disk('local')", $doc, 'PERF-SCALE-3: must record the known hardcoded-local-disk gap.');
    }
}
