<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * OBS-10: stamp every request with a UUID and propagate it to logs +
 * the response. Pre-fix, correlating a webhook failure with the
 * downstream notification job that handled it required scanning by
 * timestamp; with a request-id any log line in the request lifecycle
 * shares the same correlation key.
 *
 * Honours an inbound X-Request-Id when one looks valid (UUID), so an
 * upstream proxy / load balancer can pass its own trace id through.
 */
class AddRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header(self::HEADER, '');
        $requestId = $this->isValidUuid($incoming) ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function isValidUuid(string $value): bool
    {
        return $value !== '' && preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }
}
