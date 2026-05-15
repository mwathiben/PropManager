<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\ReportService;
use Tests\TestCase;

/**
 * Phase-27 BI-CI-3: coverage ratchet. Every report type returned by
 * ReportService::supportedTypes() must have:
 *   - a golden fixture at tests/Fixtures/reports/expected/{type}.json
 *   - a perf budget at config/perf.php['report_query_budget_ms'][type]
 *
 * Adding a new type without these is a CI failure — forces the
 * Phase-27 discipline (golden first, perf budget always).
 */
class Phase27CoverageTest extends TestCase
{
    public function test_every_supported_report_type_has_a_golden_fixture(): void
    {
        foreach (ReportService::supportedTypes() as $type) {
            $path = base_path("tests/Fixtures/reports/expected/{$type}.json");
            $this->assertFileExists(
                $path,
                "BI-CI-3: ReportService::supportedTypes() includes '{$type}' but no golden fixture exists at {$path}. Run `php artisan reports:write-golden --type={$type}` and commit the file.",
            );
        }
    }

    public function test_every_supported_report_type_has_a_perf_budget(): void
    {
        $budgets = (array) config('perf.report_query_budget_ms', []);
        foreach (ReportService::supportedTypes() as $type) {
            $this->assertArrayHasKey(
                $type,
                $budgets,
                "BI-CI-3: ReportService::supportedTypes() includes '{$type}' but config/perf.php['report_query_budget_ms'] has no entry. Add a budget (start ~2000ms; tighten over time).",
            );
            $this->assertIsInt($budgets[$type]);
        }
    }
}
