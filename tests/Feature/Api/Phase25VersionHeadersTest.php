<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiVersionHeaders;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase-25 API-VERSION-2 watchdog: the `deprecated:<date>` middleware
 * emits Sunset + Deprecation headers per RFC 8594.
 */
class Phase25VersionHeadersTest extends TestCase
{
    public function test_middleware_alias_is_registered(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString(
            "'deprecated' => \\App\\Http\\Middleware\\ApiVersionHeaders::class",
            $bootstrap,
            'API-VERSION-2: the `deprecated` middleware alias must be registered.',
        );
    }

    public function test_middleware_emits_both_headers_for_a_date(): void
    {
        $middleware = new ApiVersionHeaders;
        $request = Request::create('/api/v1/test');

        $response = $middleware->handle($request, fn () => response('ok'), '2026-11-11');

        $this->assertSame('true', $response->headers->get('Deprecation'));
        $sunset = $response->headers->get('Sunset');
        $this->assertNotNull($sunset, 'API-VERSION-2: Sunset header must be emitted.');
        $this->assertStringContainsString('11 Nov 2026', $sunset);
        $this->assertStringEndsWith('GMT', $sunset, 'API-VERSION-2: Sunset must be RFC 8594 IMF-fixdate format.');
    }

    public function test_middleware_is_silent_without_a_date(): void
    {
        $middleware = new ApiVersionHeaders;
        $request = Request::create('/api/v1/test');

        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertFalse($response->headers->has('Deprecation'));
        $this->assertFalse($response->headers->has('Sunset'));
    }

    public function test_middleware_is_silent_for_a_malformed_date(): void
    {
        $middleware = new ApiVersionHeaders;
        $request = Request::create('/api/v1/test');

        // A typo in the route's middleware argument should NEVER take
        // the route down — log silently, ship the response unchanged.
        $response = $middleware->handle($request, fn () => response('ok'), 'not-a-date');

        $this->assertFalse($response->headers->has('Deprecation'));
        $this->assertFalse($response->headers->has('Sunset'));
    }
}
