<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SmsServiceInterface;
use App\Models\Building;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use App\Observers\BuildingObserver;
use App\Observers\ExpenseObserver;
use App\Observers\InvoiceObserver;
use App\Observers\LateFeeObserver;
use App\Observers\LateFeePolicyObserver;
use App\Observers\LeaseObserver;
use App\Observers\PaymentObserver;
use App\Observers\RefundObserver;
use App\Observers\TicketObserver;
use App\Observers\UnitObserver;
use App\Observers\UserObserver;
use App\Observers\WaterReadingObserver;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Repositories\Contracts\NotificationDefaultsRepositoryInterface;
use App\Repositories\NotificationConfigRepository;
use App\Repositories\NotificationDefaultsRepository;
use App\Rules\PasswordPolicy;
use App\Services\AfricasTalkingService;
use App\Services\MetricsService;
use App\Services\PaymentGatewayManager;
use App\Services\SecurityLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // OBS-11: Redis-backed counters for the payment / webhook /
        // notification hot paths. Singleton because it holds no state
        // beyond a connection name; safe to share across requests.
        $this->app->singleton(MetricsService::class, fn () => new MetricsService(
            config('metrics.connection', 'cache')
        ));

        // Register notification config repository
        $this->app->bind(
            NotificationConfigRepositoryInterface::class,
            NotificationConfigRepository::class
        );

        // Register notification defaults repository
        $this->app->bind(
            NotificationDefaultsRepositoryInterface::class,
            NotificationDefaultsRepository::class
        );

        // Register SMS service (Africa's Talking adapter)
        $this->app->bind(SmsServiceInterface::class, AfricasTalkingService::class);

        // Register payment gateway manager as singleton
        $this->app->singleton(PaymentGatewayManager::class);

        // Bind interface to default gateway
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayManager::class)->defaultGateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // CRYPTO-1: wire the project-wide password rules so every
        // Rules\Password::defaults() in controllers/Form Requests applies
        // them. Without this the PasswordPolicy class (HIBP fail-open
        // hardening from Phase-4 HANDLE-11, the 22-password banlist, the
        // 12-char minimum, and the symbol enforcement) is dead code.
        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->rules([new PasswordPolicy]));

        // Register model observers
        Building::observe(BuildingObserver::class);
        Unit::observe(UnitObserver::class);
        WaterReading::observe(WaterReadingObserver::class);
        Ticket::observe(TicketObserver::class);
        User::observe(UserObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);

        // Finance cache invalidation observers
        Expense::observe(ExpenseObserver::class);
        LateFee::observe(LateFeeObserver::class);
        LateFeePolicy::observe(LateFeePolicyObserver::class);
        Lease::observe(LeaseObserver::class);
        Refund::observe(RefundObserver::class);

        // Prevent lazy loading in non-production to catch N+1 queries
        // Violations are logged to security channel instead of throwing
        if (! app()->environment('production')) {
            Model::preventLazyLoading();

            Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
                Log::channel('security')->warning('N+1 Query Detected', [
                    'model' => get_class($model),
                    'relation' => $relation,
                    'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))
                        ->filter(fn ($frame) => isset($frame['file']) && ! str_contains($frame['file'], '/vendor/'))
                        ->take(5)
                        ->map(fn ($frame) => ($frame['file'] ?? '').':'.($frame['line'] ?? ''))
                        ->values()
                        ->toArray(),
                ]);
            });
        }

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

        // Payment initiation rate limiter: per-user (5/min) + per-invoice (1/min)
        RateLimiter::for('payment', function (Request $request) {
            $routeInvoice = $request->route('invoice');
            $invoiceId = is_object($routeInvoice) ? $routeInvoice->id : ($routeInvoice ?? $request->input('invoice_id'));

            $limits = [
                Limit::perMinute(5)
                    ->by('user:'.($request->user()?->id ?: $request->ip()))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many payment requests. Please try again later.',
                            'retry_after' => $headers['Retry-After'] ?? 60,
                        ], 429, $headers);
                    }),
            ];

            if ($invoiceId) {
                $limits[] = Limit::perMinute(1)
                    ->by('invoice:'.$invoiceId)
                    ->response(function (Request $request, array $headers) {
                        $logInvoice = $request->route('invoice');
                        Log::channel('security')->info('Payment rate limit hit per invoice', [
                            'invoice_id' => is_object($logInvoice) ? $logInvoice->id : ($logInvoice ?? $request->input('invoice_id')),
                            'user_id' => $request->user()?->id,
                            'ip' => $request->ip(),
                        ]);

                        return response()->json([
                            'message' => 'A payment for this invoice is already being processed. Please wait before trying again.',
                            'retry_after' => $headers['Retry-After'] ?? 60,
                        ], 429, $headers);
                    });
            }

            return $limits;
        });

        // Payment link rate limiter - stricter with security logging
        RateLimiter::for('payment-link', function (Request $request) {
            $token = $request->route('token');

            return Limit::perMinute(30)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) use ($token) {
                    Log::channel('security')->warning('Payment link rate limit exceeded', [
                        'ip' => $request->ip(),
                        'token_prefix' => $token ? substr($token, 0, 8).'...' : 'unknown',
                        'user_agent' => $request->userAgent(),
                    ]);

                    return \Inertia\Inertia::render('PaymentLink/Invalid', [
                        'reason' => 'rate_limited',
                        'message' => 'Too many requests. Please wait a moment and try again.',
                    ])->toResponse($request)->setStatusCode(429);
                });
        });

        // Export rate limiter - resource intensive operations (PDF/Excel generation)
        RateLimiter::for('export', function (Request $request) {
            $config = config('security.rate_limits.export', '5,1');
            [$maxAttempts, $decayMinutes] = explode(',', $config);

            return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many export requests. Please wait before exporting again.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Search/autocomplete rate limiter - higher limit for UX
        RateLimiter::for('search', function (Request $request) {
            $config = config('security.rate_limits.search', '30,1');
            [$maxAttempts, $decayMinutes] = explode(',', $config);

            return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many search requests. Please slow down.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // RATE-2: per-invoice + per-user notification-send limiter so a
        // landlord can't blast a tenant via 'send reminder' / 'send
        // receipt' / 'email ledger' buttons.
        RateLimiter::for('notification-send', function (Request $request) {
            $invoice = $request->route('invoice');
            $payment = $request->route('payment');
            $tenant = $request->route('tenant');
            $resourceKey = is_object($invoice) ? 'invoice:'.$invoice->id
                : (is_object($payment) ? 'payment:'.$payment->id
                : (is_object($tenant) ? 'tenant:'.$tenant->id
                : 'user:'.($request->user()?->id ?: $request->ip())));

            return [
                Limit::perMinute(3)->by($resourceKey),
                Limit::perMinute(20)->by('user:'.($request->user()?->id ?: $request->ip())),
            ];
        });

        // RATE-3: bulk-notification limiter so a compromised landlord/
        // caretaker session can't enqueue thousands of SMS/emails per
        // hour. 2/min and 20/hour per landlord.
        RateLimiter::for('bulk-notify', function (Request $request) {
            $user = $request->user();
            $landlordId = $user
                ? ($user->isCaretaker() ? $user->landlord_id : $user->id)
                : null;
            $key = $landlordId ? 'landlord:'.$landlordId : 'ip:'.$request->ip();

            return [
                Limit::perMinute(2)->by($key),
                Limit::perHour(20)->by($key),
            ];
        });

        // RATE-4: bulk-operations limiter — per-user 3/min plus a
        // serializing Cache::lock applied in the controller body so two
        // concurrent bulk-rent-adjust requests can't race.
        RateLimiter::for('bulk-ops', function (Request $request) {
            $user = $request->user();
            $key = 'user:'.($user?->id ?: $request->ip());

            return Limit::perMinute(3)->by($key);
        });

        // RATE-5: per-conversation + per-user inbox-reply limiter so a
        // landlord can't blast a tenant via 200 paid SMS/WhatsApp replies
        // in two minutes.
        RateLimiter::for('inbox-reply', function (Request $request) {
            $message = $request->route('message');
            $threadKey = is_object($message)
                ? 'thread:'.$message->user_id.':'.($request->user()?->id ?? 'anon')
                : 'user:'.($request->user()?->id ?: $request->ip());

            return [
                Limit::perMinute(5)->by($threadKey),
                Limit::perMinute(20)->by('user:'.($request->user()?->id ?: $request->ip())),
            ];
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
