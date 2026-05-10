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
