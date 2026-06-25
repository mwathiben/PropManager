<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slice-2 PR-2.4b: gate the Documenso completion webhook. Documenso emits NO
 * HMAC — it sends the configured secret verbatim in the X-Documenso-Secret
 * header. We compare it in constant time and fail closed if the platform secret
 * is unset, so an unconfigured instance can never accept an unauthenticated
 * completion that would activate a fee.
 */
class ValidateDocumensoWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('documenso.webhook_secret', '');
        $provided = (string) $request->header('X-Documenso-Secret', '');

        abort_if(
            $expected === '' || ! hash_equals($expected, $provided),
            Response::HTTP_UNAUTHORIZED,
            'Invalid Documenso webhook secret.',
        );

        return $next($request);
    }
}
