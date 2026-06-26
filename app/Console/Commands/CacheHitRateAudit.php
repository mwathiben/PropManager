<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-33 COST-CACHE-1/3: read Phase-22 CacheMetrics counters
 * (cache_hit_total + cache_miss_total) and emit per-(cache, type)
 * hit-rate gauge. If any bucket drops below the configured threshold
 * (default 0.5 = 50%), fires the low_cache_hit_rate alert via the
 * Phase-32 AlertFiringRecorder.
 *
 * The counters are LABELED in the Redis snapshot like
 * `cache_hit_total{cache=finance,type=stats}` — we parse the label
 * suffix to re-pair hits with misses for the same bucket.
 */
class CacheHitRateAudit extends Command
{
    protected $signature = 'cache:hit-rate-audit {--threshold=0.5}';

    protected $description = 'Phase-33 COST-CACHE-1: per-bucket cache hit-rate gauge + low-hit-rate alert.';

    public function handle(MetricsService $metrics, AlertFiringRecorder $recorder): int
    {
        $threshold = max(0.0, min(1.0, (float) $this->option('threshold')));
        $buckets = $this->parseBucketsFromSnapshot($metrics->snapshot());
        [$audited, $belowThreshold] = $this->auditBuckets($buckets, $threshold, $metrics);
        $this->fireOrResolveAlert($belowThreshold, $threshold, $recorder);
        $this->info(sprintf('Audited %d cache bucket(s); %d below threshold.', $audited, count($belowThreshold)));

        return self::SUCCESS;
    }

    /**
     * Parse a raw metrics snapshot into per-bucket hit/miss count arrays.
     *
     * @return array<string, array<string, int>>
     */
    private function parseBucketsFromSnapshot(array $snapshot): array
    {
        $buckets = [];

        foreach ($snapshot as $field => $value) {
            // Field format: 'cache_hit_total{cache=X,type=Y}' or
            // 'cache_miss_total{cache=X,type=Y}'. Skip non-cache fields.
            if (! preg_match('/^cache_(hit|miss)_total(?:\{(.+)\})?$/', $field, $m)) {
                continue;
            }
            $bucket = $m[2] ?? 'default';
            $kind = $m[1];
            $buckets[$bucket][$kind] = (int) $value;
        }

        return $buckets;
    }

    /**
     * Compute hit-rate ratios, emit gauges + console lines, collect below-threshold buckets.
     *
     * @param  array<string, array<string, int>>  $buckets
     * @return array{int, array<string, float>} [audited count, below-threshold map]
     */
    private function auditBuckets(array $buckets, float $threshold, MetricsService $metrics): array
    {
        $belowThreshold = [];
        $audited = 0;

        foreach ($buckets as $bucket => $counts) {
            $hits = $counts['hit'] ?? 0;
            $misses = $counts['miss'] ?? 0;
            $total = $hits + $misses;

            if ($total === 0) {
                continue;
            }

            $audited++;
            $ratio = round($hits / $total, 4);
            $labels = $this->parseLabels($bucket);
            $metrics->gauge('cache_hit_rate_ratio', $ratio, $labels);
            $this->line(sprintf('%-30s hits=%d misses=%d ratio=%.3f', $bucket, $hits, $misses, $ratio));

            if ($ratio < $threshold) {
                $belowThreshold[$bucket] = $ratio;
            }
        }

        return [$audited, $belowThreshold];
    }

    /**
     * Fire or resolve the low_cache_hit_rate alert based on audit results.
     *
     * @param  array<string, float>  $belowThreshold
     */
    private function fireOrResolveAlert(array $belowThreshold, float $threshold, AlertFiringRecorder $recorder): void
    {
        if ($belowThreshold !== []) {
            $worst = min($belowThreshold);
            $recorder->record(
                alertKey: 'low_cache_hit_rate',
                value: $worst,
                threshold: $threshold,
                metadata: ['buckets' => $belowThreshold],
            );
        } else {
            $recorder->resolve('low_cache_hit_rate');
        }
    }

    /**
     * Parse 'cache=finance,type=stats' into ['cache' => 'finance', 'type' => 'stats'].
     */
    private function parseLabels(string $bucket): array
    {
        if ($bucket === 'default') {
            return [];
        }
        $labels = [];
        foreach (explode(',', $bucket) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            if ($k !== '') {
                $labels[$k] = $v;
            }
        }

        return $labels;
    }
}
