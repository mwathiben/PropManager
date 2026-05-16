<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * OBS-11: lightweight counter service for the high-signal payment /
 * webhook / notification paths. Pre-fix we had no rate of fact:
 * if Twilio failed every webhook for an hour, nobody could put a
 * number on the impact. This is intentionally minimal — a single
 * INCR per event into a Redis hash keyed by date — so it can be
 * scraped by the dashboard or a Prometheus exporter without a
 * dependency on a metrics SaaS. Fail-closed: a Redis hiccup must
 * never break the actual payment flow, so all errors are logged
 * and swallowed.
 *
 * Phase-38 DEFER-METRICS-FALLBACK-1/2: noop-on-noredis. Before this
 * pass, every public method called Redis::connection unconditionally,
 * which logged 'Class "Redis" not found' on every request in envs
 * without phpredis. Now redisAvailable() short-circuits all five
 * methods to NOOP and logs ONCE per day (Cache::add with 24h TTL)
 * with install instructions, so the log surface stays usable.
 */
class MetricsService
{
    /**
     * Static cache for the redisAvailable() check — avoids re-probing
     * the Redis facade on every increment() call within a process.
     */
    private static ?bool $redisAvailable = null;

    public function __construct(private readonly string $connection = 'cache') {}

    /**
     * Phase-38 DEFER-METRICS-FALLBACK-1: detect whether a Redis client
     * is actually loadable in this process. Returns true when phpredis
     * extension is installed OR predis/predis composer package is
     * present. Cached statically so subsequent calls are O(1).
     */
    public static function redisAvailable(): bool
    {
        if (self::$redisAvailable !== null) {
            return self::$redisAvailable;
        }

        $hasPhpRedis = extension_loaded('redis');
        $hasPredis = class_exists(\Predis\Client::class);

        self::$redisAvailable = $hasPhpRedis || $hasPredis;

        if (! self::$redisAvailable) {
            // Log ONCE per day with install hint, not on every request.
            // Cache::add returns true exactly once until the TTL expires.
            try {
                if (Cache::add('metrics:driver-unavailable-notice', true, 86400)) {
                    Log::channel(config('logging.metrics_channel', 'stack'))->notice(
                        'metrics driver unavailable — phpredis extension and predis/predis package both missing. '
                        .'Install with `pecl install redis` (prod) or `composer require predis/predis --dev` '
                        .'(dev). MetricsService is no-op until then; the /api/metrics endpoint returns empty.',
                    );
                }
            } catch (\Throwable) {
                // If Cache itself is broken (eg first request before
                // anything is wired) just swallow — we are already in
                // a fail-soft path and must not throw upstream.
            }
        }

        return self::$redisAvailable;
    }

    /**
     * Test helper: reset the static cache so per-test environment
     * switching (with vs without Redis) doesn't carry over.
     */
    public static function resetRedisAvailabilityCache(): void
    {
        self::$redisAvailable = null;
    }

    public function increment(string $name, int $by = 1, array $labels = []): void
    {
        if (! self::redisAvailable()) {
            return;
        }
        try {
            $key = $this->bucketKey();
            $field = $this->fieldName($name, $labels);
            $client = Redis::connection($this->connection);
            $client->hincrby($key, $field, $by);
            $client->expire($key, 60 * 60 * 24 * 14); // 14d retention
        } catch (\Throwable $e) {
            Log::channel(config('logging.metrics_channel', 'stack'))->warning(
                'metrics increment failed',
                ['name' => $name, 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Phase-14 OBSERV-9: histogram observation. Records a value
     * (typically a latency in milliseconds) into exponential
     * buckets. Each bucket is its own counter; snapshot() exposes
     * them with a `_bucket{le=N}` suffix that Prometheus can scrape
     * as a histogram metric.
     *
     * Default buckets:  5, 10, 25, 50, 100, 250, 500, 1000, 2500ms
     * Each observation increments every bucket whose le >= value
     * (Prometheus convention: bucket le=X means "<=X").
     */
    public function observe(string $name, float $valueMs, array $labels = []): void
    {
        if (! self::redisAvailable()) {
            return;
        }
        try {
            $key = $this->bucketKey();
            $client = Redis::connection($this->connection);
            $buckets = config('observability.metrics.histogram_buckets_ms', [5, 10, 25, 50, 100, 250, 500, 1000, 2500]);

            foreach ($buckets as $bound) {
                if ($valueMs <= (float) $bound) {
                    $field = $this->fieldName($name.'_bucket', $labels + ['le' => (string) $bound]);
                    $client->hincrby($key, $field, 1);
                }
            }
            // +Inf bucket always increments (Prometheus convention).
            $infField = $this->fieldName($name.'_bucket', $labels + ['le' => '+Inf']);
            $client->hincrby($key, $infField, 1);

            // Sum + count: standard histogram aggregates.
            $client->hincrbyfloat($key, $this->fieldName($name.'_sum', $labels), $valueMs);
            $client->hincrby($key, $this->fieldName($name.'_count', $labels), 1);

            $client->expire($key, 60 * 60 * 24 * 14);
        } catch (\Throwable $e) {
            Log::channel(config('logging.metrics_channel', 'stack'))->warning(
                'metrics observe failed',
                ['name' => $name, 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Phase-16 QUEUE-6: gauge metric — set-not-increment. Useful for
     * point-in-time values like queue depth or failed-job counts that
     * are captured on a schedule rather than emitted per-event.
     *
     * Tracked in a separate redis hash so the export pass can render
     * `# TYPE name gauge` (vs. `counter`) for Prometheus.
     */
    public function gauge(string $name, float $value, array $labels = []): void
    {
        if (! self::redisAvailable()) {
            return;
        }
        try {
            $key = $this->gaugeKey();
            $field = $this->fieldName($name, $labels);
            $client = Redis::connection($this->connection);
            $client->hset($key, $field, (string) $value);
            $client->expire($key, 60 * 60 * 24 * 14);
        } catch (\Throwable $e) {
            Log::channel(config('logging.metrics_channel', 'stack'))->warning(
                'metrics gauge failed',
                ['name' => $name, 'error' => $e->getMessage()]
            );
        }
    }

    public function snapshot(?string $bucket = null): array
    {
        if (! self::redisAvailable()) {
            return [];
        }
        try {
            $key = $bucket ? "metrics:{$bucket}" : $this->bucketKey();

            return Redis::connection($this->connection)->hgetall($key) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Phase-14 OBSERV-1: emit the current snapshot in Prometheus
     * exposition format (text/plain; version=0.0.4). The /metrics
     * endpoint serves this directly to a Prometheus scraper. Each
     * counter becomes `propmanager_<name>{labels...} value`.
     *
     * The date-bucket suffix from the Redis key is dropped — the
     * scraper provides its own timestamp.
     */
    public function exportPrometheus(): string
    {
        $counterSnapshot = $this->snapshot();
        $gaugeSnapshot = $this->gaugeSnapshot();

        if ($counterSnapshot === [] && $gaugeSnapshot === []) {
            return "# no metrics recorded yet\n";
        }

        $lines = [];
        foreach ($counterSnapshot as $field => $value) {
            $lines[] = '# TYPE '.$this->prometheusName($field).' counter';
            $lines[] = $this->prometheusName($field).' '.((int) $value);
        }
        foreach ($gaugeSnapshot as $field => $value) {
            $lines[] = '# TYPE '.$this->prometheusName($field).' gauge';
            $lines[] = $this->prometheusName($field).' '.((float) $value);
        }

        return implode("\n", $lines)."\n";
    }

    public function gaugeSnapshot(): array
    {
        if (! self::redisAvailable()) {
            return [];
        }
        try {
            return Redis::connection($this->connection)->hgetall($this->gaugeKey()) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function prometheusName(string $field): string
    {
        // field is `name` or `name{k=v,k2=v2}` from fieldName(). Rewrite
        // the bare name to `propmanager_<name>` and preserve the label
        // braces. Prometheus naming rules disallow most punctuation in
        // the metric name; underscores are the standard separator.
        $braceStart = strpos($field, '{');
        $name = $braceStart === false ? $field : substr($field, 0, $braceStart);
        $labels = $braceStart === false ? '' : substr($field, $braceStart);
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);

        return 'propmanager_'.$name.$labels;
    }

    private function bucketKey(): string
    {
        return 'metrics:'.now()->format('Y-m-d');
    }

    private function gaugeKey(): string
    {
        return 'metrics:gauges';
    }

    private function fieldName(string $name, array $labels): string
    {
        if ($labels === []) {
            return $name;
        }

        ksort($labels);
        $tags = [];
        foreach ($labels as $k => $v) {
            $tags[] = $k.'='.$v;
        }

        return $name.'{'.implode(',', $tags).'}';
    }
}
