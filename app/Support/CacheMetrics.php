<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\MetricsService;

/**
 * Phase-22 PERF-CACHE-1: cache hit/miss counters.
 *
 * FinanceCacheService and BuildingCacheService wrap expensive computes
 * in Cache::remember. Whether those caches actually HELP — a 5-minute
 * TTL that is always missed (invalidation too aggressive, keys too
 * granular) is pure overhead — was previously only visible in debug
 * logs. This helper emits cache_hit_total / cache_miss_total through
 * the Phase-14 MetricsService so hit-rate flows through /api/metrics.
 *
 * Label cardinality is bounded on purpose: `cache` is the service name
 * ('finance'|'building'), `type` is the small method-level category
 * (e.g. 'stats'|'report'|'config'|'list') — never a per-landlord key.
 * The increment is fail-open (MetricsService swallows Redis errors).
 */
final class CacheMetrics
{
    public static function record(string $cache, string $type, bool $hit): void
    {
        try {
            app(MetricsService::class)->increment(
                $hit ? 'cache_hit_total' : 'cache_miss_total',
                1,
                ['cache' => $cache, 'type' => $type],
            );
        } catch (\Throwable) {
            // Fail-open and SILENT. MetricsService::increment already
            // swallows Redis errors, but its catch block logs via the
            // Log facade — and that itself can throw in tests that mock
            // the Log facade (NoMatchingExpectationException). A cache
            // metric is never worth interfering with a cache read, so
            // anything that escapes increment() is discarded here.
        }
    }
}
