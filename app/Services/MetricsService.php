<?php

declare(strict_types=1);

namespace App\Services;

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
 */
class MetricsService
{
    public function __construct(private readonly string $connection = 'cache') {}

    public function increment(string $name, int $by = 1, array $labels = []): void
    {
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

    public function snapshot(?string $bucket = null): array
    {
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
        $snapshot = $this->snapshot();
        if ($snapshot === []) {
            return "# no metrics recorded yet\n";
        }

        $lines = [];
        foreach ($snapshot as $field => $value) {
            $lines[] = '# TYPE '.$this->prometheusName($field).' counter';
            $lines[] = $this->prometheusName($field).' '.((int) $value);
        }

        return implode("\n", $lines)."\n";
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
