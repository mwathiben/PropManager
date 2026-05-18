<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SlowQueryLogEntry;
use App\Models\SlowQueryLogWeeklyRollup;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Phase-57 SLOW-QUERY-3: weekly rollup of slow_query_log_entries into
 * slow_query_log_weekly_rollups, plus a top-10 gauge emit.
 *
 * Idempotent: updateOrCreate keyed on (week_start_date, sql_shape,
 * landlord_id) means repeat runs of the same week's data don't dupe.
 *
 * Retention: deletes raw entries older than 30 days so the table stays
 * bounded. Rollups are kept indefinitely (softDelete only).
 */
class SlowQueryRollup extends Command
{
    protected $signature = 'slow-query:rollup';

    protected $description = 'Aggregate the last 7d of slow_query_log_entries into weekly rollups + emit top-shape gauge.';

    public const RAW_RETENTION_DAYS = 30;

    public const TOP_N_SHAPES = 10;

    public function handle(MetricsService $metrics): int
    {
        $weekStart = Carbon::now()->subDays(7)->startOfDay();
        $now = Carbon::now();

        $entries = SlowQueryLogEntry::query()
            ->whereBetween('executed_at', [$weekStart, $now])
            ->get();

        $grouped = $entries->groupBy(fn ($e) => $e->sql_shape.'|'.($e->landlord_id ?? 'null'));
        $rolledUp = 0;

        foreach ($grouped as $key => $bucket) {
            $first = $bucket->first();
            $durations = $bucket->pluck('duration_ms')->sort()->values()->all();
            $p95Index = max(0, (int) floor(0.95 * (count($durations) - 1)));
            $p95 = $durations[$p95Index] ?? 0;
            $max = $durations[count($durations) - 1] ?? 0;

            SlowQueryLogWeeklyRollup::updateOrCreate(
                [
                    'week_start_date' => $weekStart->toDateString(),
                    'sql_shape' => $first->sql_shape,
                    'landlord_id' => $first->landlord_id,
                ],
                [
                    'occurrence_count' => $bucket->count(),
                    'p95_duration_ms' => $p95,
                    'max_duration_ms' => $max,
                    'sample_sql_truncated' => substr($first->sql_shape, 0, 1000),
                ],
            );
            $rolledUp++;
        }

        // Top-N shapes by occurrence count this week → gauge.
        $topShapes = SlowQueryLogWeeklyRollup::query()
            ->where('week_start_date', $weekStart->toDateString())
            ->orderByDesc('occurrence_count')
            ->limit(self::TOP_N_SHAPES)
            ->get();

        foreach ($topShapes as $rollup) {
            $shapeHash = substr(md5($rollup->sql_shape), 0, 12);
            $metrics->gauge('slow_query_top_shape_count', $rollup->occurrence_count, [
                'shape_hash' => $shapeHash,
            ]);
        }

        $pruneCutoff = Carbon::now()->subDays(self::RAW_RETENTION_DAYS);
        $pruned = SlowQueryLogEntry::query()
            ->where('executed_at', '<', $pruneCutoff)
            ->delete();

        $this->info(sprintf(
            'Rolled up %d shape buckets; pruned %d raw entries older than %dd.',
            $rolledUp,
            $pruned,
            self::RAW_RETENTION_DAYS,
        ));

        return self::SUCCESS;
    }
}
