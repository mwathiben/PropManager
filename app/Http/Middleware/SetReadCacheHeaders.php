<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-22 PERF-CACHE-2: conditional HTTP cache headers on enumerated
 * read routes.
 *
 * Pre-Phase-22 no read endpoint sent Cache-Control or ETag — every
 * navigation re-fetched in full even when nothing changed. This
 * middleware applies, ONLY to routes explicitly listed in
 * config('observability.read_cache.routes'), a content-based ETag (so
 * an unchanged page round-trips as a bodyless 304) plus a
 * `private, must-revalidate` Cache-Control — `private` so a shared
 * cache never stores per-user content, `must-revalidate` so the ETag
 * is always re-checked (which still re-runs auth on the server).
 *
 * It is deliberately allow-list-driven: a route gets cache headers only
 * if it is in the config. Auth-sensitive / per-request-fresh routes
 * (dashboards with live counts, anything with a CSRF token in the
 * body) must never be added.
 *
 * Phase-57 L7-CACHE-1: appends Vary 'Accept, Accept-Encoding, Cookie'
 * so a shared cache (Cloudflare / Fastly) doesn't serve one tenant's
 * HTML to another even if the URL matches. Accept covers content-
 * negotiation, Accept-Encoding covers gzip/br variants, Cookie covers
 * per-tenant fragmentation (the session cookie carries the
 * landlord_id binding).
 *
 * Phase-57 L7-CACHE-2: $shared=true picks the public/s-maxage variant
 * for truly tenant-agnostic routes (marketing landing, robots.txt).
 * The Vary header is still set so a future content-negotiation change
 * doesn't break shared-cache correctness.
 */
class SetReadCacheHeaders
{
    public const VARY_HEADER = 'Accept, Accept-Encoding, Cookie';

    public function handle(Request $request, Closure $next, string $variant = 'private'): Response
    {
        $response = $next($request);
        $shared = $variant === 'shared';

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        $routes = config('observability.read_cache.routes', []);
        if ($routeName === null || ! array_key_exists($routeName, $routes)) {
            return $response;
        }

        $maxAge = (int) $routes[$routeName];

        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            $etag = '"'.md5($content).'"';
            $response->setEtag($etag);

            $ifNoneMatch = (string) $request->headers->get('If-None-Match', '');
            if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
                // Unchanged — 304, body dropped. The client still hit
                // the server, so auth was still enforced.
                $response->setNotModified();
            }
        }

        $cacheControl = $shared
            ? "public, s-maxage={$maxAge}, max-age=60"
            : "private, must-revalidate, max-age={$maxAge}";
        $response->headers->set('Cache-Control', $cacheControl);
        $response->headers->set('Vary', self::VARY_HEADER);

        return $response;
    }
}
