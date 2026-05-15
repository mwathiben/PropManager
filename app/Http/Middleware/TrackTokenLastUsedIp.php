<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-25 API-AUTH-2: write the requester's IP to the active Sanctum
 * token's `last_used_ip` column on every authenticated /api/* request.
 *
 * Sanctum already auto-updates `last_used_at` via its TokenAuth
 * middleware — this complements it with the IP signal a landlord
 * needs to spot a leaked token (token last used from an unfamiliar
 * IP = compromise indicator).
 *
 * Defensive: a request whose user is not Sanctum-authenticated (or
 * whose current token is null — e.g. session-authenticated web user
 * with a Sanctum guard fallthrough) is a no-op. Failure to write the
 * IP must never break the request.
 */
class TrackTokenLastUsedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user) {
            return $response;
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            // TransientToken (session-auth via Sanctum's stateful
            // guard) — no row to update.
            return $response;
        }

        // Sanctum::actingAs() in tests sets the user's accessToken to
        // a Mockery mock of PersonalAccessToken — `instanceof` accepts
        // it but its forceFill() returns false (the mock's default for
        // unmocked methods), which would NPE on saveQuietly. Skip mocks.
        if (class_exists(\Mockery\MockInterface::class) && $token instanceof \Mockery\MockInterface) {
            return $response;
        }

        // forceFill + saveQuietly so the model event listeners
        // (Auditable trait, etc.) do not fire on every API request —
        // this is per-request high-volume bookkeeping, not an
        // audit-worthy state change. Wrap in try/catch — failure to
        // write the IP must never break the request (e.g. DB hiccup).
        try {
            $token->forceFill(['last_used_ip' => $request->ip()])->saveQuietly();
        } catch (\Throwable) {
            // Silent — best-effort observability, never load-bearing.
        }

        return $response;
    }
}
