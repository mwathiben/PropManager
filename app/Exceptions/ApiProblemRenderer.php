<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Phase-25 API-ERROR-1: convert any exception bubbling up out of an
 * /api/v1/* request to an RFC 7807 problem+json response.
 *
 * Industry convention (Stripe / GitHub / Slack) is the problem+json
 * shape: { type (absolute URI), title, status, detail, instance }.
 * PropManager's pre-Phase-25 errors were 5 different shapes — one per
 * exception class — so integrator code needed N parsers. This
 * renderer collapses them all into a single envelope with stable
 * type URIs.
 *
 * ValidationException keeps its `errors` field (existing client
 * compatibility) but gains the surrounding problem+json envelope.
 *
 * RATELIMIT-2 piggybacks: ThrottleRequestsException is rendered with
 * `retry_after_seconds` so consumers can pace themselves without
 * second-guessing the Retry-After header.
 */
class ApiProblemRenderer
{
    private const TYPE_BASE = 'https://propmanager.test/errors/';

    public function render(Request $request, Throwable $e): ?JsonResponse
    {
        if (! $this->shouldRender($request)) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->fromValidation($request, $e),
            $e instanceof ThrottleRequestsException => $this->fromThrottle($request, $e),
            $e instanceof HttpResponseException => $this->fromHttpResponse($request, $e),
            $e instanceof AuthenticationException => $this->problem($request, 'unauthenticated', 'Unauthenticated', 401, $e->getMessage()),
            $e instanceof AuthorizationException => $this->problem($request, 'forbidden', 'Forbidden', 403, $e->getMessage() ?: 'This action is unauthorized.'),
            $e instanceof ModelNotFoundException => $this->problem($request, 'not-found', 'Resource not found', 404, $e->getMessage() ?: 'The requested resource could not be found.'),
            $e instanceof TokenMismatchException => $this->problem($request, 'csrf-mismatch', 'CSRF token mismatch', 419, 'Session token has expired. Refresh and retry.'),
            $e instanceof HttpExceptionInterface => $this->fromHttpException($request, $e),
            default => $this->problem($request, 'server-error', 'Internal server error', 500, app()->environment('production') ? 'An unexpected error occurred.' : $e->getMessage()),
        };
    }

    public function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson() && $request->is('api*');
    }

    private function fromValidation(Request $request, ValidationException $e): JsonResponse
    {
        $payload = $this->envelope($request, 'validation-failed', 'Validation failed', 422, 'The request payload failed validation. See `errors` for the per-field detail.');
        $payload['errors'] = $e->errors();

        return $this->respond($payload);
    }

    /**
     * Laravel's named rate-limit `->response()` callbacks throw
     * HttpResponseException wrapping a pre-built response. For 429s
     * we rewrite to problem+json (RATELIMIT-2); for other status
     * codes we pass through so the limiter's intent is preserved.
     */
    private function fromHttpResponse(Request $request, HttpResponseException $e): ?JsonResponse
    {
        $inner = $e->getResponse();
        if ($inner->getStatusCode() !== 429) {
            return null;
        }

        $retryAfter = (int) ($inner->headers->get('Retry-After') ?? 60);

        $payload = $this->envelope(
            $request,
            'rate-limit-exceeded',
            'Too many requests',
            429,
            "Bucket exhausted — wait {$retryAfter} seconds before retrying.",
        );
        $payload['retry_after_seconds'] = $retryAfter;

        $response = $this->respond($payload);
        foreach ($inner->headers->all() as $name => $values) {
            if (in_array(strtolower($name), ['content-type', 'content-length'], true)) {
                continue;
            }
            $response->headers->set($name, $values);
        }

        return $response;
    }

    private function fromThrottle(Request $request, ThrottleRequestsException $e): JsonResponse
    {
        $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);

        $payload = $this->envelope(
            $request,
            'rate-limit-exceeded',
            'Too many requests',
            429,
            sprintf('Bucket exhausted — wait %d seconds before retrying.', $retryAfter),
        );
        $payload['retry_after_seconds'] = $retryAfter;

        $response = $this->respond($payload);

        // Carry Laravel's existing throttle headers so the response is
        // still consistent with the X-RateLimit envelope set by
        // ThrottleRequests (which fires before this renderer).
        foreach ($e->getHeaders() as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    private function fromHttpException(Request $request, HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $slug = match ($status) {
            400 => 'bad-request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not-found',
            405 => 'method-not-allowed',
            410 => 'gone',
            419 => 'csrf-mismatch',
            422 => 'unprocessable-entity',
            500 => 'server-error',
            502 => 'bad-gateway',
            503 => 'service-unavailable',
            default => 'http-'.$status,
        };
        $title = match ($status) {
            400 => 'Bad request',
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Resource not found',
            405 => 'Method not allowed',
            410 => 'Resource gone',
            419 => 'CSRF token mismatch',
            422 => 'Validation failed',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
            default => 'HTTP '.$status,
        };

        return $this->problem($request, $slug, $title, $status, $e->getMessage() ?: $title);
    }

    private function problem(Request $request, string $slug, string $title, int $status, string $detail): JsonResponse
    {
        return $this->respond($this->envelope($request, $slug, $title, $status, $detail));
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(Request $request, string $slug, string $title, int $status, string $detail): array
    {
        return [
            'type' => self::TYPE_BASE.$slug,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->path() !== '' ? '/'.$request->path() : '/',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(array $payload): JsonResponse
    {
        return new JsonResponse(
            $payload,
            (int) ($payload['status'] ?? Response::HTTP_INTERNAL_SERVER_ERROR),
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
