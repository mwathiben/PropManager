<?php

use App\Exceptions\DomainException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
    })->create();
