<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\User;
use App\Services\BuildingCacheService;
use App\Services\FinanceCacheService;
use App\Services\MetricsService;
use App\Support\CacheMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-22 PERF-CACHE-1 + PERF-CACHE-2 watchdog.
 *
 * CI has no live Redis, so the hit/miss tests assert the MetricsService
 * is CALLED with the right metric + bounded labels (via a mock) rather
 * than asserting Redis counter state. The HTTP-header tests hit a real
 * enumerated read route.
 */
class Phase22CacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{0:string,1:int,2:array<string,string>}>
     */
    private function captureIncrements(callable $body): array
    {
        $calls = [];
        $metrics = $this->createMock(MetricsService::class);
        $metrics->method('increment')->willReturnCallback(
            function (string $name, int $by, array $labels) use (&$calls): void {
                $calls[] = [$name, $by, $labels];
            },
        );
        $this->app->instance(MetricsService::class, $metrics);

        $body();

        return $calls;
    }

    public function test_finance_cache_emits_hit_and_miss_counters(): void
    {
        $calls = $this->captureIncrements(function (): void {
            // Cold call -> miss. Warm call -> hit.
            FinanceCacheService::rememberStats('hub', 4242, fn () => ['x' => 1]);
            FinanceCacheService::rememberStats('hub', 4242, fn () => ['x' => 1]);
        });

        $names = array_column($calls, 0);
        $this->assertContains('cache_miss_total', $names, 'PERF-CACHE-1: a cold finance cache call must increment cache_miss_total.');
        $this->assertContains('cache_hit_total', $names, 'PERF-CACHE-1: a warm finance cache call must increment cache_hit_total.');

        foreach ($calls as [$name, $by, $labels]) {
            $this->assertSame(['cache' => 'finance', 'type' => 'stats'], $labels, 'PERF-CACHE-1: finance stats counters must carry the finance/stats labels.');
        }
    }

    public function test_building_cache_emits_hit_and_miss_counters(): void
    {
        $calls = $this->captureIncrements(function (): void {
            BuildingCacheService::rememberList(7373, fn () => collect());
            BuildingCacheService::rememberList(7373, fn () => collect());
        });

        $names = array_column($calls, 0);
        $this->assertContains('cache_miss_total', $names, 'PERF-CACHE-1: a cold building cache call must increment cache_miss_total.');
        $this->assertContains('cache_hit_total', $names, 'PERF-CACHE-1: a warm building cache call must increment cache_hit_total.');

        foreach ($calls as [$name, $by, $labels]) {
            $this->assertSame(['cache' => 'building', 'type' => 'list'], $labels);
        }
    }

    public function test_cache_metric_labels_are_bounded(): void
    {
        // PERF-CACHE-1: labels must be exactly {cache, type} — never a
        // per-landlord key, which would explode cardinality.
        $calls = $this->captureIncrements(function (): void {
            CacheMetrics::record('finance', 'report', true);
        });

        $this->assertCount(1, $calls);
        [$name, $by, $labels] = $calls[0];
        $this->assertSame('cache_hit_total', $name);
        $this->assertSame(1, $by);
        $this->assertSame(['cache', 'type'], array_keys($labels), 'PERF-CACHE-1: cache metric labels must be exactly [cache, type].');
    }

    public function test_enumerated_read_route_sends_cache_headers(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($user)->get('/help');
        $response->assertSuccessful();

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl, 'PERF-CACHE-2: /help (an enumerated read route) must carry a Cache-Control header.');
        $this->assertStringContainsString('private', $cacheControl, 'PERF-CACHE-2: read-route caching must be private (never shared-cacheable).');
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'), 'PERF-CACHE-2: an enumerated read route must carry an ETag for conditional 304s.');
    }

    public function test_middleware_returns_304_on_matching_if_none_match(): void
    {
        // The 304 round-trip is tested at the middleware level, not via
        // /help: every Inertia HTML response carries a per-request CSP
        // nonce (Phase-15 SecurityHeaders), so its content — and thus
        // its content-based ETag — changes every request. The
        // Cache-Control max-age is the real win for those pages; the
        // ETag/304 path pays off for any non-nonce'd route added to the
        // allow-list later. This drives the middleware with fixed
        // content so the conditional-request contract is deterministic.
        config(['observability.read_cache.routes' => ['help.index' => 300]]);
        $middleware = new \App\Http\Middleware\SetReadCacheHeaders;

        $makeRequest = function (string $ifNoneMatch = ''): \Illuminate\Http\Request {
            $request = \Illuminate\Http\Request::create('/help', 'GET');
            if ($ifNoneMatch !== '') {
                $request->headers->set('If-None-Match', $ifNoneMatch);
            }
            $route = (new \Illuminate\Routing\Route(['GET'], '/help', []))->name('help.index');
            $request->setRouteResolver(fn () => $route);

            return $request;
        };

        $first = $middleware->handle(
            $makeRequest(),
            fn () => new \Symfony\Component\HttpFoundation\Response('stable help content', 200),
        );
        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag, 'PERF-CACHE-2: the middleware must set an ETag.');

        $second = $middleware->handle(
            $makeRequest($etag),
            fn () => new \Symfony\Component\HttpFoundation\Response('stable help content', 200),
        );
        $this->assertSame(304, $second->getStatusCode(), 'PERF-CACHE-2: a matching If-None-Match must yield a 304.');
    }

    public function test_sensitive_routes_are_not_in_read_cache_allowlist(): void
    {
        // PERF-CACHE-2 regression-lock: auth-sensitive / per-request-fresh
        // routes must NEVER get cache headers.
        $allowlisted = array_keys(config('observability.read_cache.routes', []));

        foreach (['dashboard', 'login', 'register', 'invoices.index', 'tenants.index'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $allowlisted,
                "PERF-CACHE-2: '{$forbidden}' must NOT be in the read-cache allow-list — it is auth-sensitive or per-request-fresh.",
            );
        }
    }

    public function test_stampede_guard_computes_once_and_caches(): void
    {
        // PERF-CACHE-3: a miss computes; a subsequent hit serves the
        // cached value WITHOUT re-running the (expensive) callback.
        $key = 'phase22:stampede:'.uniqid();
        $runs = 0;
        $compute = function () use (&$runs): string {
            $runs++;

            return 'computed-value';
        };

        $first = \App\Support\CacheStampedeGuard::remember($key, 60, $compute);
        $second = \App\Support\CacheStampedeGuard::remember($key, 60, $compute);

        $this->assertSame('computed-value', $first);
        $this->assertSame('computed-value', $second);
        $this->assertSame(1, $runs, 'PERF-CACHE-3: the compute callback must run exactly once — the second call is a cache hit.');
    }

    public function test_stampede_guard_falls_back_when_lock_is_unavailable(): void
    {
        // PERF-CACHE-3: if the stampede lock cannot be acquired within
        // the wait window, the caller computes directly rather than
        // blocking — it must NEVER hang or throw.
        $key = 'phase22:stampede:'.uniqid();

        // Hold the stampede lock so remember() cannot acquire it.
        $heldLock = \Illuminate\Support\Facades\Cache::lock("stampede:{$key}", 10);
        $this->assertTrue($heldLock->get(), 'fixture: the test must be able to hold the lock.');

        try {
            $value = \App\Support\CacheStampedeGuard::remember(
                $key,
                60,
                fn () => 'fallback-computed',
                lockSeconds: 10,
                waitSeconds: 1,
            );
            $this->assertSame('fallback-computed', $value, 'PERF-CACHE-3: a lock-timeout caller must fall back to a direct compute.');
        } finally {
            $heldLock->release();
        }
    }

    public function test_finance_cache_routes_through_stampede_guard(): void
    {
        // PERF-CACHE-3 regression-lock: the hot finance cache methods
        // must use the stampede guard, not a bare Cache::remember.
        $source = file_get_contents(base_path('app/Services/FinanceCacheService.php'));
        $this->assertStringContainsString(
            'CacheStampedeGuard::remember',
            $source,
            'PERF-CACHE-3: FinanceCacheService rememberStats/rememberReport must route through CacheStampedeGuard.',
        );
    }
}
