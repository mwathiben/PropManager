<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Application rate-limiter definitions, extracted from AppServiceProvider
 * (M2 decomposition). AppServiceProvider::boot() calls configure().
 * Behaviour locked by Phase25RateLimitTest et al.
 */
class RateLimiterConfigurator
{
    public function configure(): void
    {
        $this->registerLoginLimiter();
        $this->registerRegisterLimiter();
        $this->registerPasswordResetLimiter();
        $this->registerTwoFactorLimiter();
        $this->registerTelemetryLimiter();
        $this->registerMessagesLimiter();
        $this->registerReactionsLimiter();
        $this->registerFileUploadLimiter();
        $this->registerApiLimiter();
        $this->registerCspReportLimiter();
        $this->registerInvitationLimiter();
        $this->registerSensitiveLimiter();
        $this->registerPaymentLimiter();
        $this->registerPaymentLinkLimiter();
        $this->registerExportLimiter();
        $this->registerSearchLimiter();
        $this->registerNotificationSendLimiter();
        $this->registerBulkNotifyLimiter();
        $this->registerBulkOpsLimiter();
        $this->registerInboxReplyLimiter();
        $this->registerBankVerifyLimiter();
        $this->registerProviderTestLimiter();
        $this->registerPdfRenderLimiter();
        $this->registerLegalHoldLimiter();
    }

    /** @return array{int,int} */
    private function parseRateLimitConfig(string $configKey, string $default): array
    {
        $config = config($configKey, $default);
        [$maxAttempts, $decayMinutes] = explode(',', $config);

        return [(int) $maxAttempts, (int) $decayMinutes];
    }

    private function jsonTooManyResponse(string $message): \Closure
    {
        return function (Request $request, array $headers) use ($message) {
            return response()->json([
                'message' => $message,
                'retry_after' => $headers['Retry-After'] ?? 60,
            ], 429, $headers);
        };
    }

    private function registerLoginLimiter(): void
    {
        RateLimiter::for('login', function (Request $request) {
            [$max, $decay] = $this->parseRateLimitConfig('security.rate_limits.login', '5,1');

            return Limit::perMinutes($decay, $max)
                ->by($request->input('email').'|'.$request->ip())
                ->response($this->jsonTooManyResponse('Too many login attempts. Please try again later.'));
        });
    }

    private function registerRegisterLimiter(): void
    {
        RateLimiter::for('register', function (Request $request) {
            [$max, $decay] = $this->parseRateLimitConfig('security.rate_limits.register', '3,1');

            return Limit::perMinutes($decay, $max)
                ->by($request->ip())
                ->response($this->jsonTooManyResponse('Too many registration attempts. Please try again later.'));
        });
    }

    private function registerPasswordResetLimiter(): void
    {
        RateLimiter::for('password-reset', function (Request $request) {
            [$max, $decay] = $this->parseRateLimitConfig('security.rate_limits.password_reset', '3,1');

            return Limit::perMinutes($decay, $max)
                ->by($request->input('email', $request->ip()))
                ->response($this->jsonTooManyResponse('Too many password reset requests. Please try again later.'));
        });
    }

    private function registerTwoFactorLimiter(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response($this->jsonTooManyResponse('Too many 2FA verification attempts. Please try again later.'));
        });
    }

    private function registerTelemetryLimiter(): void
    {
        // Phase-64 TELEMETRY-WIRE-1: clients flush on visibilitychange/beforeunload.
        RateLimiter::for('telemetry', function (Request $request) {
            return Limit::perMinute(60)
                ->by((string) ($request->user()?->id ?? $request->ip()))
                ->response($this->jsonTooManyResponse('Telemetry rate limit exceeded.'));
        });
    }

    private function registerMessagesLimiter(): void
    {
        // Phase-63 INBOX-MOD-3: inbox compose throttle.
        RateLimiter::for('messages', function (Request $request) {
            $perMinute = (int) config('inbox.rate_limit.per_minute', 20);

            return Limit::perMinute($perMinute)
                ->by((string) ($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) {
                    app(\App\Services\MetricsService::class)->gauge('inbox_rate_limit_hits_count', 1);

                    return response()->json([
                        'message' => 'Too many messages. Please slow down.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });
    }

    private function registerReactionsLimiter(): void
    {
        // Phase-71 REACTIONS: own budget — UI invites rapid taps.
        RateLimiter::for('reactions', function (Request $request) {
            $perMinute = (int) config('inbox.reactions_rate_limit.per_minute', 120);

            return Limit::perMinute($perMinute)
                ->by((string) ($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) {
                    app(\App\Services\MetricsService::class)->gauge('inbox_rate_limit_hits_count', 1);

                    return response()->json([
                        'message' => 'Too many reactions. Please slow down.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });
    }

    private function registerFileUploadLimiter(): void
    {
        RateLimiter::for('file-upload', function (Request $request) {
            [$max, $decay] = $this->parseRateLimitConfig('security.rate_limits.file_upload', '10,1');

            return Limit::perMinutes($decay, $max)
                ->by($request->user()?->id ?: $request->ip())
                ->response($this->jsonTooManyResponse('Too many file uploads. Please try again later.'));
        });
    }

    private function registerApiLimiter(): void
    {
        // Phase-25 API-RATELIMIT-3: token rate_limit_multiplier scales the bucket.
        RateLimiter::for('api', function (Request $request) {
            [$maxAttempts, $decayMinutes] = $this->parseRateLimitConfig('security.rate_limits.api', '60,1');

            $token = $request->user()?->currentAccessToken();
            if ($token && isset($token->rate_limit_multiplier)) {
                $multiplier = (float) $token->rate_limit_multiplier;
                if ($multiplier > 0.0) {
                    $maxAttempts = (int) max(1, round($maxAttempts * $multiplier));
                }
            }

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->user()?->id ?: $request->ip());
        });
    }

    private function registerCspReportLimiter(): void
    {
        // Phase-15 FRONT-6: prevents write-amplification on security_logs.
        RateLimiter::for('csp-report', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }

    private function registerInvitationLimiter(): void
    {
        RateLimiter::for('invitation', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response($this->jsonTooManyResponse('Too many invitation attempts. Please try again later.'));
        });
    }

    private function registerSensitiveLimiter(): void
    {
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->user()?->id ?: $request->ip())
                ->response($this->jsonTooManyResponse('Too many requests. Please wait before trying again.'));
        });
    }

    private function registerPaymentLimiter(): void
    {
        // RATE-1: per-user (5/min) + per-invoice (1/min).
        RateLimiter::for('payment', function (Request $request) {
            $routeInvoice = $request->route('invoice');
            $invoiceId = is_object($routeInvoice) ? $routeInvoice->id : ($routeInvoice ?? $request->input('invoice_id'));

            $limits = [
                Limit::perMinute(5)
                    ->by('user:'.($request->user()?->id ?: $request->ip()))
                    ->response($this->jsonTooManyResponse('Too many payment requests. Please try again later.')),
            ];

            if ($invoiceId) {
                $limits[] = $this->buildPerInvoicePaymentLimit($invoiceId);
            }

            return $limits;
        });
    }

    private function buildPerInvoicePaymentLimit(int|string $invoiceId): Limit
    {
        return Limit::perMinute(1)
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

    private function registerPaymentLinkLimiter(): void
    {
        // Stricter with security logging.
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
    }

    private function registerExportLimiter(): void
    {
        RateLimiter::for('export', function (Request $request) {
            [$max, $decay] = $this->parseRateLimitConfig('security.rate_limits.export', '5,1');

            return Limit::perMinutes($decay, $max)
                ->by($request->user()?->id ?: $request->ip())
                ->response($this->jsonTooManyResponse('Too many export requests. Please wait before exporting again.'));
        });
    }

    private function registerSearchLimiter(): void
    {
        RateLimiter::for('search', function (Request $request) {
            [$max, $decay] = $this->parseRateLimitConfig('security.rate_limits.search', '30,1');

            return Limit::perMinutes($decay, $max)
                ->by($request->user()?->id ?: $request->ip())
                ->response($this->jsonTooManyResponse('Too many search requests. Please slow down.'));
        });
    }

    private function registerNotificationSendLimiter(): void
    {
        // RATE-2: per-resource + per-user; prevents blast via send-reminder buttons.
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
    }

    private function registerBulkNotifyLimiter(): void
    {
        // RATE-3: 2/min and 20/hour per landlord.
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
    }

    private function registerBulkOpsLimiter(): void
    {
        // RATE-4: 3/min per-user; controller body applies Cache::lock for concurrency.
        RateLimiter::for('bulk-ops', function (Request $request) {
            $key = 'user:'.($request->user()?->id ?: $request->ip());

            return Limit::perMinute(3)->by($key);
        });
    }

    private function registerInboxReplyLimiter(): void
    {
        // RATE-5: per-conversation + per-user; prevents SMS/WhatsApp burst.
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

    private function registerBankVerifyLimiter(): void
    {
        // RATE-10: Paystack bank verify is metered + a name-enumeration vector.
        RateLimiter::for('bank-verify', function (Request $request) {
            $key = 'user:'.($request->user()?->id ?: $request->ip());

            return [
                Limit::perMinute(3)->by($key),
                Limit::perHour(30)->by($key),
            ];
        });
    }

    private function registerProviderTestLimiter(): void
    {
        // RATE-11: testProvider/previewTemplate round-trip to SMS/email provider.
        RateLimiter::for('provider-test', function (Request $request) {
            $key = 'user:'.($request->user()?->id ?: $request->ip());

            return Limit::perMinute(5)->by($key);
        });
    }

    private function registerPdfRenderLimiter(): void
    {
        // RATE-12: DOMPDF/Snappy is CPU-bound; protects worker pool.
        RateLimiter::for('pdf-render', function (Request $request) {
            $key = 'user:'.($request->user()?->id ?: $request->ip());

            return Limit::perMinute(15)->by($key);
        });
    }

    private function registerLegalHoldLimiter(): void
    {
        // Phase-65 BULK-HOLD-2: one POST can mint 500 rows + bust cache 4×.
        RateLimiter::for('legal-hold', function (Request $request) {
            return Limit::perMinute(10)
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });
    }
}
