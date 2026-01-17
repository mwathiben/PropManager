<?php

namespace App\Providers;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use App\Observers\BuildingObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Observers\TicketObserver;
use App\Observers\UnitObserver;
use App\Observers\UserObserver;
use App\Observers\WaterReadingObserver;
use App\Services\SecurityLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register SecurityLogger as a singleton
        $this->app->singleton(SecurityLogger::class, function ($app) {
            return new SecurityLogger($app['request']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register model observers
        Building::observe(BuildingObserver::class);
        Unit::observe(UnitObserver::class);
        WaterReading::observe(WaterReadingObserver::class);
        Ticket::observe(TicketObserver::class);
        User::observe(UserObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);

        // Configure rate limiters
        $this->configureRateLimiting();

        // Validate security configuration in production
        $this->validateProductionSecurity();
    }

    /**
     * Configure rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Login rate limiter - stricter to prevent brute force
        RateLimiter::for('login', function (Request $request) {
            $config = config('security.rate_limits.login', '5,1');
            [$maxAttempts, $decayMinutes] = explode(',', $config);

            return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
                ->by($request->input('email').'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Registration rate limiter
        RateLimiter::for('register', function (Request $request) {
            $config = config('security.rate_limits.register', '3,1');
            [$maxAttempts, $decayMinutes] = explode(',', $config);

            return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many registration attempts. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Password reset rate limiter
        RateLimiter::for('password-reset', function (Request $request) {
            $config = config('security.rate_limits.password_reset', '3,1');
            [$maxAttempts, $decayMinutes] = explode(',', $config);

            return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
                ->by($request->input('email', $request->ip()))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many password reset requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Two-factor authentication rate limiter
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many 2FA verification attempts. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // File upload rate limiter
        RateLimiter::for('file-upload', function (Request $request) {
            $config = config('security.rate_limits.file_upload', '10,1');
            [$maxAttempts, $decayMinutes] = explode(',', $config);

            return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many file uploads. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // API rate limiter (general)
        RateLimiter::for('api', function (Request $request) {
            $config = config('security.rate_limits.api', '60,1');
            [$maxAttempts, $decayMinutes] = explode(',', $config);

            return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Invitation acceptance rate limiter
        RateLimiter::for('invitation', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many invitation attempts. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Sensitive operations rate limiter (password change, delete account, etc.)
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests. Please wait before trying again.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Payment initiation rate limiter (stricter for financial transactions)
        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many payment requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });
    }

    /**
     * Validate that critical security settings are properly configured in production.
     */
    protected function validateProductionSecurity(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $warnings = [];

        // Check debug mode
        if (config('app.debug')) {
            $warnings[] = 'APP_DEBUG is enabled in production! This exposes sensitive information.';
        }

        // Check session encryption
        if (! config('session.encrypt')) {
            $warnings[] = 'SESSION_ENCRYPT is disabled. Sessions should be encrypted in production.';
        }

        // Check HTTPS/secure cookies
        if (! config('session.secure')) {
            $warnings[] = 'SESSION_SECURE_COOKIE is disabled. Cookies should be secure in production.';
        }

        // Check HSTS
        if (! config('security.headers.hsts_enabled')) {
            $warnings[] = 'HSTS is disabled. Enable it for HTTPS-only enforcement.';
        }

        // Check app key
        if (empty(config('app.key'))) {
            $warnings[] = 'APP_KEY is not set! Application encryption will not work.';
        }

        // Log warnings
        foreach ($warnings as $warning) {
            Log::channel('security')->warning('[SECURITY CONFIG] '.$warning);
        }

        // In strict mode, throw exception for critical issues
        if (config('app.debug') && $this->app->environment('production')) {
            throw new \RuntimeException(
                'CRITICAL: Debug mode is enabled in production. Set APP_DEBUG=false in your .env file.'
            );
        }
    }
}
