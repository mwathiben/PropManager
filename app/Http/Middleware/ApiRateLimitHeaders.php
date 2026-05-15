<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-25 API-RATELIMIT-1: ensure every throttled API response carries
 * the full X-RateLimit envelope (Limit / Remaining / Reset).
 *
 * Laravel's built-in ThrottleRequests emits X-RateLimit-Limit and
 * X-RateLimit-Remaining on every throttled response, and Reset on 429
 * only. Industry convention (Stripe / GitHub / Twilio) is to surface
 * Reset on every throttled response so consumers can pace themselves
 * BEFORE hitting 429.
 *
 * The pipeline order in Laravel 12 puts api-group middleware OUTSIDE
 * route-level middleware: ThrottleRequests runs INSIDE this middleware
 * and its addHeaders() fires before our handle() sees the response —
 * but the response Limit header is not on the response object yet at
 * the moment our after-phase executes, because Laravel's pipeline
 * resolves the response chain differently than naive nesting suggests.
 * To dodge ordering dependency entirely, we resolve the bucket from
 * the route's middleware declaration and emit a conservative Reset
 * estimate (now + decay) on every /api/* response whose route is
 * throttled. The exact Reset on 429 is already set by Laravel and
 * we never overwrite an existing value.
 */
class ApiRateLimitHeaders
{
    /**
     * Per-bucket decay window in seconds. Mirrors the RateLimiter::for
     * declarations registered in the application service provider.
     */
    private const DECAY_SECONDS = [
        'api' => 60,
        'login' => 60,
        'register' => 3600,
        'two-factor' => 60,
        'payment' => 60,
        'csp-report' => 60,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        // Never overwrite an existing Reset (Laravel sets it on 429
        // alongside Retry-After, with the exact timestamp the bucket
        // refills — we defer to that).
        if ($response->headers->has('X-RateLimit-Reset')) {
            return $response;
        }

        $bucket = $this->resolveBucketName($request);
        if ($bucket === null) {
            return $response;
        }

        $decay = self::DECAY_SECONDS[$bucket] ?? 60;
        $response->headers->set('X-RateLimit-Reset', (string) (time() + $decay));

        return $response;
    }

    /**
     * Read the throttle bucket name from the route's middleware stack.
     * Format: `throttle:<name>` (named limiter) or `throttle:<max>,<minutes>`
     * (inline declaration — fall back to `api`).
     *
     * Returns null when the route is not throttled (rare on /api/*
     * but possible for unauthenticated public endpoints).
     */
    private function resolveBucketName(Request $request): ?string
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        foreach ($route->gatherMiddleware() as $entry) {
            if (! is_string($entry) || ! str_starts_with($entry, 'throttle:')) {
                continue;
            }

            $arg = substr($entry, strlen('throttle:'));
            // Inline syntax (e.g. throttle:60,1) is not a named limiter
            // — fall through to the 'api' default decay.
            if (preg_match('/^\d/', $arg)) {
                return 'api';
            }

            return $arg;
        }

        return null;
    }
}
