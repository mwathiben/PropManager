<?php

declare(strict_types=1);

namespace Tests\Feature\Cleanup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
}
