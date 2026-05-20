<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\ReportService;
use Database\Seeders\GoldenReportFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase-27 BI-CI-1: golden-query regression test.
 *
 * Each report type's SQL output, run against the deterministic
 * GoldenReportFixtureSeeder dataset, must match the committed JSON
 * at tests/Fixtures/reports/expected/{type}.json. Any diff means
 * the report SQL changed semantics — operator must re-run
 * `php artisan reports:write-golden` and commit the new fixtures
 * (the diff is the prose explanation in the commit message).
 */
class Phase27GoldenQueriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: string}>
     */
    public static function reportTypes(): array
    {
        return array_map(fn ($t) => [$t], ReportService::supportedTypes());
    }

    #[DataProvider('reportTypes')]
    public function test_report_type_matches_golden_output(string $type): void
    {
        $landlordId = 999000;
        (new GoldenReportFixtureSeeder($landlordId))->run();

        $expectedPath = base_path("tests/Fixtures/reports/expected/{$type}.json");
        if (! file_exists($expectedPath)) {
            $this->markTestSkipped("No golden fixture at {$expectedPath}. Run `php artisan reports:write-golden --type={$type}` and commit the file to enable the gate.");
        }

        $service = app(ReportService::class);
        // Roundtrip through json so the comparison matches the on-disk
        // encoding's type erasure (0.0 → 0, Carbon → string). Without
        // this, int vs float mismatches surface as false-positives.
        $live = json_decode(
            (string) json_encode($this->normalise($service->exportData($landlordId, $type, 'year'))),
            true,
        );
        $expected = json_decode((string) file_get_contents($expectedPath), true);

        $this->assertSame(
            $expected,
            $live,
            "BI-CI-1: report '{$type}' diverged from {$expectedPath}. Re-run `php artisan reports:write-golden --type={$type}` and commit the diff with a one-line rationale.",
        );
    }

    /**
     * Mirror the normalisation in WriteReportGoldens so live + golden
     * are compared after the same noise-stripping pass.
     */
    private function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (in_array($k, ['date_range', 'period', 'generated_at'], true)) {
                    $out[$k] = '__VARYING__';

                    continue;
                }
                $out[$k] = $this->normalise($v);
            }

            return $out;
        }

        return $value;
    }
}
