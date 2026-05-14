<?php

use App\Exceptions\DomainException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        // Phase-11 DEPLOY-8: removed Laravel's static /up shortcut so
        // there is exactly one liveness endpoint — /api/v1/health
        // (OBS-2) which probes DB / Redis / queue / WebhookDeadLetter.
        // Point all load balancers + uptime monitors at /api/v1/health.
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // OBS-10: stamp request-id BEFORE anything else so downstream
        // logs (security, schedule, payments, notifications) all share
        // the same correlation key for the request lifecycle.
        $middleware->prepend(\App\Http\Middleware\AddRequestId::class);

        // Security headers should be applied to all responses
        $middleware->web(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\BlockArchivedUsers::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // Phase-22 PERF-SLO-1: emits http_request_ms from terminate().
            \App\Http\Middleware\RecordRequestLatency::class,
        ]);

        // Phase-22 PERF-SLO-1: same latency instrumentation on the API
        // surface (webhooks, the mobile/integration endpoints).
        $middleware->api(append: [
            \App\Http\Middleware\RecordRequestLatency::class,
        ]);

        // Exclude webhook routes from CSRF verification (they use signature verification)
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        // Register middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'plan' => \App\Http\Middleware\CheckPlanLimits::class,
            'onboarding.complete' => \App\Http\Middleware\EnsureOnboardingComplete::class,
            'two-factor' => \App\Http\Middleware\TwoFactorChallenge::class,
            'kyc.complete' => \App\Http\Middleware\EnsureTenantKycComplete::class,
            'payment.verified' => \App\Http\Middleware\EnsurePaymentVerified::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'webhook.mpesa' => \App\Http\Middleware\ValidateMpesaWebhook::class,
            'webhook.paystack' => \App\Http\Middleware\ValidatePaystackWebhook::class,
            'webhook.intasend' => \App\Http\Middleware\ValidateIntaSendWebhook::class,
            'block.archived' => \App\Http\Middleware\BlockArchivedUsers::class,
            // RATE-9: single-use signed link enforcement (above + table).
            'signed.once' => \App\Http\Middleware\EnsureSignedLinkSingleUse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // OBS-1: route every unhandled exception through Sentry. When
        // SENTRY_LARAVEL_DSN is empty (local dev, CI), the SDK is a
        // silent no-op — so this is safe to leave on globally.
        Integration::handles($exceptions);

        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                    'context' => $e->getContext(),
                ], $e->getStatusCode());
            }

            return null;
        });

        // Phase-21 DEFER-AUTHZ-4: render dedicated Inertia 403/404 pages
        // for HTML requests instead of the raw Symfony error overlay.
        // API requests keep their JSON shape; local dev keeps Ignition
        // so stack traces stay debuggable.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || app()->environment('local')) {
                return $response;
            }

            return match ($response->getStatusCode()) {
                403 => Inertia::render('Errors/403')->toResponse($request)->setStatusCode(403),
                404 => Inertia::render('Errors/404')->toResponse($request)->setStatusCode(404),
                default => $response,
            };
        });
    })->create();
