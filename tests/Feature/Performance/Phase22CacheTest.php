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
}
