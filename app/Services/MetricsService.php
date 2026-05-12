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
