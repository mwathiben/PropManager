<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\ReportService;
use Database\Seeders\GoldenReportFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-27 BI-CI-2: per-report-endpoint perf budget gate.
 *
 * For each report type: seed the deterministic dataset, run the
 * report 5 times, take the median ms. Median > config budget = fail.
 * Median is preferred over mean because the first run pays
 * connection-warm-up overhead that the later runs don't.
 *
 * Re-baseline: edit config/perf.php['report_query_budget_ms'] with
 * a one-line commit-message rationale.
 */
class Phase27PerfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: string}>
     */
    public static function reportTypes(): array
    {
        return array_map(fn ($t) => [$t], ReportService::supportedTypes());
    }

    /**
     * @dataProvider reportTypes
     */
    public function test_report_type_runs_within_perf_budget(string $type): void
    {
        $landlordId = 999000;
        (new GoldenReportFixtureSeeder($landlordId))->run();

        $budgets = (array) config('perf.report_query_budget_ms', []);
        $budget = $budgets[$type] ?? null;
        $this->assertIsInt(
            $budget,
            "BI-CI-2: config/perf.php must declare a report_query_budget_ms entry for '{$type}'. Phase27CoverageTest enforces this ratchet.",
        );

        $service = app(ReportService::class);
        $samples = [];
        for ($i = 0; $i < 5; $i++) {
            $start = hrtime(true);
            $service->exportData($landlordId, $type, 'year');
            $samples[] = (hrtime(true) - $start) / 1_000_000.0;
        }

        sort($samples);
        $median = $samples[2];

        $this->assertLessThanOrEqual(
            (float) $budget,
            $median,
            "BI-CI-2: '{$type}' median ".round($median, 1)."ms > budget {$budget}ms. Either optimise the query or re-baseline config/perf.php with a one-line rationale.",
        );
    }
}
