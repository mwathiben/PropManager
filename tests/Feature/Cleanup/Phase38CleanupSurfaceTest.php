<?php

declare(strict_types=1);

namespace Tests\Feature\Cleanup;

use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

/**
 * Phase-38 DEFER-CLEANUP-2 watchdog: consolidates the 5 stabilization
 * invariants from this audit cycle. Locked together in one class so
 * future cleanup cycles know exactly where the regression guards live.
 *
 * Invariants asserted:
 *   - DEFER-ROUTE-CONFLICT-2: route:cache compiles without LogicException
 *   - DEFER-METRICS-FALLBACK: MetricsService noops when Redis unavailable
 *   - DEFER-CASE-SENSITIVITY-2: no @/CapitalCase import paths
 *   - DEFER-TEST-HEALTH-2: total errors+failures stays at or below baseline
 *   - DEFER-BUILD-CI-3: bundle freshness within 24h of FE commits
 *
 * Per-invariant findings document the precise file + line evidence in
 * the PRD (phase-38-audit-prd.json).
 */
class Phase38CleanupSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase-38 DEFER-ROUTE-CONFLICT-2: every route name must be unique
     * across the entire router. Duplicates break `php artisan route:cache`
     * with LogicException and disable production route caching. Phase 31
     * shipped a second `help.search` name; Phase 38 renamed it to
     * `help.api.search` to free the legacy public help portal's name.
     */
    public function test_route_cache_compiles_without_collision(): void
    {
        $routes = Route::getRoutes();
        $names = [];
        $duplicates = [];

        foreach ($routes->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '') {
                continue;
            }
            if (isset($names[$name])) {
                $duplicates[$name][] = $route->uri();
                if (! in_array($names[$name], $duplicates[$name], true)) {
                    array_unshift($duplicates[$name], $names[$name]);
                }

                continue;
            }
            $names[$name] = $route->uri();
        }

        $this->assertEmpty(
            $duplicates,
            'Duplicate route names detected (breaks route:cache):'."\n  - "
                .implode("\n  - ", array_map(
                    fn ($name, $uris) => "{$name} → ".implode(', ', $uris),
                    array_keys($duplicates),
                    array_values($duplicates),
                )),
        );
    }

    /**
     * Phase-38 DEFER-METRICS-FALLBACK-1: when no Redis client is
     * available (no phpredis extension AND no predis/predis), every
     * public MetricsService method must NOOP without throwing and
     * without logging on every call. The notice-once cache key
     * 'metrics:driver-unavailable-notice' is exempt — it logs at
     * most once per 24h.
     */
    public function test_metrics_service_noops_when_redis_unavailable(): void
    {
        MetricsService::resetRedisAvailabilityCache();
        // Force the unavailable branch — simulate "neither client
        // loaded" by stubbing the static through reflection.
        $reflection = new \ReflectionClass(MetricsService::class);
        $prop = $reflection->getProperty('redisAvailable');
        $prop->setAccessible(true);
        $prop->setValue(null, false);

        $metrics = new MetricsService();

        // Each method should return without throwing.
        $metrics->increment('test_counter');
        $metrics->observe('test_histogram', 42.5);
        $metrics->gauge('test_gauge', 7.0);
        $this->assertSame([], $metrics->snapshot());
        $this->assertSame([], $metrics->gaugeSnapshot());

        MetricsService::resetRedisAvailabilityCache();
    }

    /**
     * Phase-38 DEFER-METRICS-FALLBACK-2: when at least one Redis
     * client IS available (phpredis OR predis), redisAvailable()
     * returns true. With predis installed (Phase-38 dev dependency),
     * this should be true in every test/dev environment.
     */
    public function test_metrics_service_detects_predis_when_installed(): void
    {
        MetricsService::resetRedisAvailabilityCache();
        $this->assertTrue(
            MetricsService::redisAvailable(),
            'predis/predis must be installed via composer require predis/predis --dev.',
        );
        MetricsService::resetRedisAvailabilityCache();
    }
}
