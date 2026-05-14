<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase-21 DEFER-OBSERV-3: parse the storage/logs/slow-query-*.log
 * files emitted by SlowQueryServiceProvider (Phase-15 PERF-6) and
 * surface aggregated counts + p95 / p99 latency by query shape. The
 * report consumes Phase-15 instrumentation evidence to inform the
 * Phase-21 DEFER-PERF-1 SoftDeletes-index decision: if deleted_at
 * IS NULL queries dominate the slow-query log, the index workaround
 * (is_active shadow column) is worth shipping. If not, the
 * non-adoption decision is documented in
 * docs/runbooks/perf-5-non-adoption.md.
 *
 * Usage:
 *   php artisan slow-query:report --since=60d --top=20
 *   php artisan slow-query:report --since=14d --json
 */
class SlowQueryReport extends Command
{
    protected $signature = 'slow-query:report '
        .'{--since=60d : Lookback window (1h, 24h, 7d, 30d, 60d)} '
        .'{--top=20 : Show top N query shapes} '
        .'{--json : Emit JSON instead of a table}';

    protected $description = 'Phase-21 DEFER-OBSERV-3: aggregate slow-query log entries by parameterized query shape with p50/p95/p99 latency.';

    public function handle(): int
    {
        $since = $this->parseSince((string) $this->option('since'));
        $top = max(1, (int) $this->option('top'));

        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            $this->warn('No log directory; nothing to aggregate.');

            return self::SUCCESS;
        }

        $files = glob($logDir.DIRECTORY_SEPARATOR.'slow-query-*.log') ?: [];
        if (empty($files)) {
            $this->info('slow-query:report: no slow-query log files found. SlowQueryServiceProvider is a no-op unless SLOW_QUERY_THRESHOLD_MS is set in env.');

            return self::SUCCESS;
        }

        $entries = $this->collectEntries($files, $since);
        if (empty($entries)) {
            $this->info("slow-query:report: 0 entries in the last {$this->option('since')} window.");

            return self::SUCCESS;
        }

        $aggregated = $this->aggregateByShape($entries, $top);

        if ($this->option('json')) {
            $this->line(json_encode($aggregated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'slow-query:report: %d entries over %d files (since=%s, top=%d)',
            count($entries),
            count($files),
            $this->option('since'),
            $top,
        ));

        $this->table(
            ['Count', 'p50', 'p95', 'p99', 'Kind', 'Shape (truncated)'],
            array_map(fn ($row) => [
                $row['count'],
                $row['p50_ms'].'ms',
                $row['p95_ms'].'ms',
                $row['p99_ms'].'ms',
                $row['kind'],
                $row['shape'],
            ], $aggregated),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{at: \DateTimeImmutable, duration: float, kind: string, shape: string}>
     */
    private function collectEntries(array $files, \DateTimeImmutable $since): array
    {
        $entries = [];
        foreach ($files as $file) {
            $handle = fopen($file, 'r');
            if (! $handle) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $entry = $this->parseLine($line);
                if ($entry === null) {
                    continue;
                }
                if ($entry['at'] < $since) {
                    continue;
                }
                $entries[] = $entry;
            }
            fclose($handle);
        }

        return $entries;
    }

    /**
     * Parse a single slow-query log line. Format produced by
     * SlowQueryServiceProvider: monolog default with context
     * containing time/sql/kind keys.
     *
     * @return array{at: \DateTimeImmutable, duration: float, kind: string, shape: string}|null
     */
    private function parseLine(string $line): ?array
    {
        if (! preg_match('/^\[([^\]]+)\].*?slow.*?query.*?:?\s*(.*)$/i', $line, $m)) {
            return null;
        }

        try {
            $at = new \DateTimeImmutable($m[1]);
        } catch (\Exception) {
            return null;
        }

        $body = $m[2];
        $duration = 0.0;
        if (preg_match('/"time"\s*:\s*([0-9.]+)/', $body, $dm)) {
            $duration = (float) $dm[1];
        }
        $kind = 'other';
        if (preg_match('/"kind"\s*:\s*"([^"]+)"/', $body, $km)) {
            $kind = $km[1];
        }
        $sql = '';
        if (preg_match('/"sql"\s*:\s*"([^"]*)"/', $body, $sm)) {
            $sql = $sm[1];
        }

        return [
            'at' => $at,
            'duration' => $duration,
            'kind' => $kind,
            'shape' => $this->parameterize($sql),
        ];
    }

    private function parameterize(string $sql): string
    {
        $sql = preg_replace('/\b\d+\b/', '?', $sql);
        $sql = preg_replace('/\'[^\']*\'/', '?', $sql);
        $sql = preg_replace('/\s+/', ' ', $sql);

        return trim((string) $sql);
    }

    /**
     * @return array<int, array{count: int, p50_ms: int, p95_ms: int, p99_ms: int, kind: string, shape: string}>
     */
    private function aggregateByShape(array $entries, int $top): array
    {
        $buckets = [];
        foreach ($entries as $entry) {
            $key = $entry['kind'].'|'.substr($entry['shape'], 0, 200);
            $buckets[$key] ??= ['durations' => [], 'kind' => $entry['kind'], 'shape' => $entry['shape']];
            $buckets[$key]['durations'][] = $entry['duration'];
        }

        $rows = [];
        foreach ($buckets as $bucket) {
            $durations = $bucket['durations'];
            sort($durations);
            $count = count($durations);
            $rows[] = [
                'count' => $count,
                'p50_ms' => (int) round($durations[max(0, (int) floor($count * 0.50) - 1)]),
                'p95_ms' => (int) round($durations[max(0, (int) floor($count * 0.95) - 1)]),
                'p99_ms' => (int) round($durations[max(0, (int) floor($count * 0.99) - 1)]),
                'kind' => $bucket['kind'],
                'shape' => substr($bucket['shape'], 0, 80),
            ];
        }

        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($rows, 0, $top);
    }

    private function parseSince(string $window): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable;
        if (preg_match('/^(\d+)([hd])$/', $window, $m)) {
            $n = (int) $m[1];
            $unit = $m[2] === 'h' ? 'hours' : 'days';

            return $now->modify("-{$n} {$unit}");
        }

        return $now->modify('-60 days');
    }
}
