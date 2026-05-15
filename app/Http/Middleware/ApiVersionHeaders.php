<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-25 API-VERSION-2: emit Sunset + Deprecation headers on
 * deprecated API routes.
 *
 * Usage (in routes/api.php):
 *
 *   Route::get('/v1/landlord/reports/arrears', [...])
 *       ->middleware('deprecated:2026-11-11')
 *       ->name('api.v1.reports.arrears');
 *
 * After this date the route's responses carry:
 *
 *   Deprecation: true
 *   Sunset: Wed, 11 Nov 2026 23:59:59 GMT     ← RFC 8594 IMF-fixdate
 *
 * Consumers should log a warning when they see Deprecation: true and
 * plan migration before the Sunset date. The contract (6-month
 * minimum window, consumer notification, post-sunset 410) is
 * documented in docs/runbooks/api-deprecation.md.
 *
 * Edge case: a malformed date string in the middleware argument is a
 * configuration bug, not a runtime user-facing error — we log it via
 * a no-op (the route still responds normally, headers omitted) so a
 * deploy with a typo doesn't take the API down.
 */
class ApiVersionHeaders
{
    public function handle(Request $request, Closure $next, string $sunsetDate = ''): Response
    {
        $response = $next($request);

        if ($sunsetDate === '') {
            return $response;
        }

        try {
            $sunset = Carbon::parse($sunsetDate)->endOfDay();
        } catch (\Throwable) {
            return $response;
        }

        $response->headers->set('Deprecation', 'true');
        // RFC 8594 IMF-fixdate (e.g. "Wed, 11 Nov 2026 23:59:59 GMT").
        $response->headers->set('Sunset', $sunset->copy()->utc()->format('D, d M Y H:i:s').' GMT');

        return $response;
    }
}
