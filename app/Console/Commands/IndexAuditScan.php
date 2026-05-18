<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Services\Sre\IndexAuditCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-57 INDEX-AUDIT-1: nightly EXPLAIN audit of hot-path queries.
 *
 * For each catalog entry, runs EXPLAIN against the rendered SQL +
 * bindings; emits db_missing_index_hint{query_label} gauge when a full
 * tablescan ('type'=='ALL') OR a large-fraction-of-table row count
 * (rows > 5000) is detected.
 *
 * Idempotent + read-only — EXPLAIN doesn't execute the query, just plans
 * it. Skips queries that fail to plan (a label might reference a column
 * a migration removed; the cron logs + continues).
 */
class IndexAuditScan extends Command
{
    protected $signature = 'index-audit:scan';

    protected $description = 'Run EXPLAIN against curated hot-path queries + emit db_missing_index_hint gauge when a full tablescan is detected.';

    public const ROWS_THRESHOLD = 5000;

    public function handle(IndexAuditCatalog $catalog, MetricsService $metrics): int
    {
        $hintCount = 0;
        $skipped = 0;
        $clean = 0;

        foreach ($catalog->queries() as $label => $factory) {
            try {
                $builder = $factory();
                $sql = $builder->toSql();
                $bindings = $builder->getBindings();

                $explainRows = DB::select('EXPLAIN '.$sql, $bindings);
                $hint = $this->detectHint($explainRows);

                if ($hint) {
                    $metrics->gauge('db_missing_index_hint', 1.0, ['query_label' => $label]);
                    $this->warn("hint: {$label} ({$hint})");
                    $hintCount++;
                } else {
                    $metrics->gauge('db_missing_index_hint', 0.0, ['query_label' => $label]);
                    $clean++;
                }
            } catch (\Throwable $e) {
                $this->error("skipped {$label}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->info(sprintf(
            'Index audit scan complete: %d hints / %d clean / %d skipped.',
            $hintCount,
            $clean,
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * Returns a short hint string when the EXPLAIN output suggests a
     * missing index, or null if the plan is acceptable.
     *
     * @param  array<int, object>  $explainRows
     */
    private function detectHint(array $explainRows): ?string
    {
        foreach ($explainRows as $row) {
            $type = is_object($row) ? ($row->type ?? null) : null;
            $rows = is_object($row) ? ($row->rows ?? 0) : 0;

            if ($type === 'ALL') {
                return 'full tablescan (type=ALL)';
            }
            if ((int) $rows > self::ROWS_THRESHOLD) {
                return "large row estimate (rows={$rows})";
            }
        }

        return null;
    }
}
