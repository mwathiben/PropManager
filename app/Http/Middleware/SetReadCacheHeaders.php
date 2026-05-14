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
 */
class SetReadCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

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

        $response->headers->set('Cache-Control', "private, must-revalidate, max-age={$maxAge}");

        return $response;
    }
}
