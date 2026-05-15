<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReportService;
use Database\Seeders\GoldenReportFixtureSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-27 BI-CI-1: generate the committed golden fixtures.
 *
 * Operator runs this after an intentional change to a report SQL
 * (the diff IS the prose explanation in the commit). The
 * Phase27GoldenQueriesTest watchdog then asserts the live SQL output
 * matches the committed JSON.
 *
 * The command seeds a deterministic dataset, runs each report type,
 * pretty-prints to tests/Fixtures/reports/expected/{type}.json.
 *
 * Idempotent: re-running overwrites the goldens in-place. Operator
 * commits the new files alongside the SQL change.
 */
class WriteReportGoldens extends Command
{
    protected $signature = 'reports:write-golden
        {--type= : Limit to a single report type}
        {--landlord-id=999000 : Synthetic landlord id used by the seeder}';

    protected $description = 'Phase-27 BI-CI-1: write tests/Fixtures/reports/expected/{type}.json from the seeded golden dataset.';

    public function handle(ReportService $reports): int
    {
        $landlordId = (int) $this->option('landlord-id');
        $only = $this->option('type');

        $this->info('Seeding golden dataset…');
        DB::transaction(function () use ($landlordId) {
            (new GoldenReportFixtureSeeder($landlordId))->run();
        });

        $types = ReportService::supportedTypes();
        if ($only !== null) {
            $types = array_values(array_filter($types, fn ($t) => $t === $only));
            if ($types === []) {
                $this->error("Unknown type '{$only}'. Supported: ".implode(', ', ReportService::supportedTypes()));

                return self::FAILURE;
            }
        }

        $outDir = base_path('tests/Fixtures/reports/expected');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0o755, true);
        }

        foreach ($types as $type) {
            $payload = $reports->exportData($landlordId, $type, 'year');
            $normalised = $this->normalise($payload);
            $path = $outDir.'/'.$type.'.json';
            file_put_contents(
                $path,
                json_encode($normalised, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
            );
            $this->info("Wrote {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * Strip non-deterministic noise (date_range start/end depending on
     * 'now') so the goldens compare cleanly. Replace any value that
     * looks like 'YYYY-MM-DD' or an ISO timestamp under known keys
     * with a sentinel.
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
