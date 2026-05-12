<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MetricsService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Phase-15 PERF-6: slow-query logging via DB::listen. Production
 * query patterns are invisible to operators today — when a tenant
 * complains "the dashboard is slow", ops cannot pull the actual SQL.
 *
 * Activation: SLOW_QUERY_THRESHOLD_MS=N env var (no default — leave
 * unset to disable). Recommended production value: 250-500ms.
 *
 * Output:
 *   - MetricsService::observe('db_query_ms', $time, [statement_kind])
 *     so the Phase-14 OBSERV-9 histogram captures the distribution
 *   - Log::channel('slow-query')->warning(...) with the SQL + bindings
 *     (already masked via Phase-13 DPA-6 SensitiveDataMaskingProcessor
 *     when wired on the slow-query channel)
 *
 * Listening on every query is cheap — DB::listen runs in-process.
 * The threshold gate filters out fast queries before any logging.
 */
class SlowQueryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $thresholdMs = (int) env('SLOW_QUERY_THRESHOLD_MS', 0);
        if ($thresholdMs <= 0) {
            return;
        }

        DB::listen(function (QueryExecuted $event) use ($thresholdMs) {
            if ($event->time < $thresholdMs) {
                return;
            }

            try {
                app(MetricsService::class)->observe(
                    'db_query_ms',
                    $event->time,
                    ['kind' => $this->statementKind($event->sql)],
                );
            } catch (\Throwable) {
                // MetricsService is fail-closed on Redis hiccups;
                // wrap defensively so a metrics outage doesn't
                // break query execution.
            }

            Log::channel(config('logging.slow_query_channel', 'stack'))->warning(
                'slow query',
                [
                    'time_ms' => $event->time,
                    'connection' => $event->connectionName,
                    'sql' => substr($event->sql, 0, 200),
                    'bindings_count' => count($event->bindings),
                ]
            );
        });
    }

    private function statementKind(string $sql): string
    {
        $trimmed = ltrim($sql);
        if (preg_match('/^(select|insert|update|delete|alter|create|drop)\b/i', $trimmed, $m)) {
            return strtolower($m[1]);
        }

        return 'other';
    }
}
