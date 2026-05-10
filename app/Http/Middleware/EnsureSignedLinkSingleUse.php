<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * RATE-9: enforce single-use semantics on top of Laravel's signed
 * middleware. A forwarded email (or one-click unsubscribe link the user
 * shared by accident) cannot be replayed: the first request consumes
 * the signature in signed_link_uses, and subsequent requests get 403.
 *
 * The signature itself stays as Laravel's URL::signedRoute output —
 * we hash it before persisting so the table doesn't store the live
 * token, only its fingerprint.
 */
class EnsureSignedLinkSingleUse
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = (string) $request->query('signature', '');

        if ($signature === '' || ! $request->hasValidSignature()) {
            throw new InvalidSignatureException;
        }

        $hash = hash('sha256', $signature);
        $expiresAt = $request->query('expires');

        try {
            DB::table('signed_link_uses')->insert([
                'signature_hash' => $hash,
                'route' => substr((string) $request->path(), 0, 191),
                'consumed_at' => now(),
                'expires_at' => $expiresAt
                    ? \Carbon\Carbon::createFromTimestampUTC((int) $expiresAt)
                    : now()->addDays(30),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                abort(403, 'This link has already been used.');
            }

            throw $e;
        }

        return $next($request);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
