<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Http\Middleware\SetReadCacheHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Phase-57 L7-CACHE-1/2 watchdog. Validates Vary header invariant +
 * cache.read.shared variant.
 */
class Phase57L7CacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_variant_emits_private_cache_control_and_vary(): void
    {
        config([
            'observability.read_cache.routes' => ['test.cache.private' => 60],
        ]);

        $request = Request::create('/test', 'GET');
        $request->setRouteResolver(fn () => new class
        {
            public function getName(): ?string
            {
                return 'test.cache.private';
            }
        });

        $next = fn () => new Response('payload', 200);
        $response = (new SetReadCacheHeaders)->handle($request, $next);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);
        $this->assertSame(
            SetReadCacheHeaders::VARY_HEADER,
            $response->headers->get('Vary'),
        );
    }

    public function test_shared_variant_emits_public_smaxage_and_vary(): void
    {
        config([
            'observability.read_cache.routes' => ['marketing.landing' => 300],
        ]);

        $request = Request::create('/landing', 'GET');
        $request->setRouteResolver(fn () => new class
        {
            public function getName(): ?string
            {
                return 'marketing.landing';
            }
        });

        $next = fn () => new Response('landing-html', 200);
        $response = (new SetReadCacheHeaders)->handle($request, $next, 'shared');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('s-maxage=300', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);
        $this->assertSame(
            SetReadCacheHeaders::VARY_HEADER,
            $response->headers->get('Vary'),
        );
    }

    public function test_non_get_response_unaffected(): void
    {
        config([
            'observability.read_cache.routes' => ['test.cache.private' => 60],
        ]);

        $request = Request::create('/test', 'POST');
        $request->setRouteResolver(fn () => new class
        {
            public function getName(): ?string
            {
                return 'test.cache.private';
            }
        });

        $next = fn () => new Response('payload', 200);
        $response = (new SetReadCacheHeaders)->handle($request, $next);

        // Middleware bypasses non-GET — Vary header not set by us.
        $this->assertNull($response->headers->get('Vary'));
        $this->assertStringNotContainsString('max-age', (string) $response->headers->get('Cache-Control'));
    }

    public function test_route_not_in_allow_list_unaffected(): void
    {
        config([
            'observability.read_cache.routes' => [],
        ]);

        $request = Request::create('/test', 'GET');
        $request->setRouteResolver(fn () => new class
        {
            public function getName(): ?string
            {
                return 'not.in.allow.list';
            }
        });

        $next = fn () => new Response('payload', 200);
        $response = (new SetReadCacheHeaders)->handle($request, $next);

        $this->assertNull($response->headers->get('Vary'));
        $this->assertStringNotContainsString('max-age', (string) $response->headers->get('Cache-Control'));
    }

    public function test_vary_header_includes_cookie_for_per_tenant_fragmentation(): void
    {
        // Critical: Cookie MUST be in the Vary list so a shared cache can't
        // serve one tenant's HTML to another.
        $this->assertStringContainsString(
            'Cookie',
            SetReadCacheHeaders::VARY_HEADER,
            'Vary header missing Cookie — shared-cache tenant leak risk.',
        );
    }
}
