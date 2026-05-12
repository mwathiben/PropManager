<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Http\Request;

/**
 * Phase-15 PERF-3: cap the per_page parameter on paginated endpoints.
 * Before this trait, `?per_page=99999` returned 99999 rows in one
 * response — a cheap DoS on the worker (build the response, serialise
 * 99999 rows to JSON, ship over the network). RATE limited the rate
 * of requests but not the work per request.
 *
 * Controllers use:
 *
 *   $perPage = $this->resolvePerPage($request);   // 1..max
 *   Model::paginate($perPage);
 *
 * Explicit MAX is checked at the call site, defaulting to 200. The
 * trait emits a validation-rejection (422) if per_page is non-numeric,
 * silently caps if numeric but over the max (defensive — a numeric
 * over-cap is a likely UI bug, not an attack).
 */
trait LimitsPerPage
{
    /**
     * Return a safe per-page integer in [1, $max].
     *
     * If the client supplied per_page non-numerically (e.g. ?per_page=foo)
     * an Illuminate validator failure is thrown (422). If they supplied
     * a value out of range, we silently clamp.
     */
    public function resolvePerPage(Request $request, int $default = 15, int $max = 200): int
    {
        $raw = $request->input('per_page');
        if ($raw === null || $raw === '') {
            return $default;
        }

        // Reject non-numeric explicitly — silently coercing 'foo' to
        // 0 then clamping to 1 would mask client bugs.
        $request->validate([
            'per_page' => 'sometimes|integer|min:1',
        ]);

        $value = (int) $raw;

        return max(1, min($value, $max));
    }
}
