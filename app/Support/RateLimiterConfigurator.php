<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Application rate-limiter definitions, extracted from AppServiceProvider
 * (M2 decomposition). Holds every RateLimiter::for(...) registration
 * (login / registration / password-reset / 2FA / telemetry / inbox /
 * reactions / uploads / ...). AppServiceProvider::boot() calls configure().
 * Verbatim move — behaviour is locked by the throttle / rate-limit feature
 * tests (Phase25RateLimitTest et al.).
 */
class RateLimiterConfigurator
{
    public function configure(): void
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

        // Phase-64 TELEMETRY-WIRE-1: PWA telemetry ingress throttle.
        // 60/min/user is generous — clients flush on visibilitychange +
        // beforeunload (at most a handful of beacons per session).
        RateLimiter::for('telemetry', function (Request $request) {
            return Limit::perMinute(60)
                ->by((string) ($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Telemetry rate limit exceeded.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Phase-63 INBOX-MOD-3: inbox compose throttle. 20/min by
        // authenticated user — well above the ~1 msg/min back-and-forth
        // peak but enough to absorb a compromised-account burst.
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

        // Phase-71 REACTIONS: a far more generous limiter than 'messages'.
        // A reaction toggle is cheap and the UI invites rapid taps, so it
        // gets its own budget instead of sharing the 20/min compose limit.
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

        // API rate limiter (general).
        //
        // Phase-25 API-RATELIMIT-3: when the request is authenticated
        // via a Sanctum personal access token, the token's
        // rate_limit_multiplier scales the bucket. Trusted integration
        // partners (multiplier 2.0) get 2x the headroom without
        // mutating the global config; one-off abuse cases can be
        // throttled by setting the multiplier <1.0 on a specific
        // token. Multiplier <= 0 falls back to 1.0 (defensive — no
        // legit value would zero a partner out; that's revoke's job).
        RateLimiter::for('api', function (Request $request) {
            $config = config('security.rate_limits.api', '60,1');
            [$maxAttempts, $decayMinutes] = explode(',', $config);
            $maxAttempts = (int) $maxAttempts;

            $token = $request->user()?->currentAccessToken();
            if ($token && isset($token->rate_limit_multiplier)) {
                $multiplier = (float) $token->rate_limit_multiplier;
                if ($multiplier > 0.0) {
                    $maxAttempts = (int) max(1, round($maxAttempts * $multiplier));
                }
            }

            return Limit::perMinutes((int) $decayMinutes, $maxAttempts)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Phase-15 FRONT-6: CSP violation reports. One compromised
        // browser or extension can fire many violations per minute;
        // 30/min is a reasonable cap that surfaces a genuine
        // misconfiguration without becoming a write-amplification
        // attack on security_logs.
        RateLimiter::for('csp-report', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
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

        // RATE-10: bank-verify limiter — Paystack bank verification is
        // metered by Paystack and also a name-enumeration vector if left
        // wide open. 3/min per user, with a 30/hour ceiling per landlord.
        RateLimiter::for('bank-verify', function (Request $request) {
            $user = $request->user();
            $key = 'user:'.($user?->id ?: $request->ip());

            return [
                Limit::perMinute(3)->by($key),
                Limit::perHour(30)->by($key),
            ];
        });

        // RATE-11: provider-test limiter — testProvider/previewTemplate
        // both round-trip to the SMS/email provider; a tight bound stops
        // a runaway UI from burning the daily provider quota.
        RateLimiter::for('provider-test', function (Request $request) {
            $key = 'user:'.($request->user()?->id ?: $request->ip());

            return Limit::perMinute(5)->by($key);
        });

        // RATE-12: PDF rendering is CPU-bound (DOMPDF/Snappy); a bound
        // protects worker pool from an automation that loops download.
        // 15/min lets a landlord export 15 receipts/leases without
        // tripping the tighter export limiter (5/min) which is reserved
        // for full-account dumps.
        RateLimiter::for('pdf-render', function (Request $request) {
            $key = 'user:'.($request->user()?->id ?: $request->ip());

            return Limit::perMinute(15)->by($key);
        });

        // Phase-65 BULK-HOLD-2: bulk hold endpoints have higher
        // attacker leverage than single-subject (one POST can mint
        // 500 rows + bust the cache 4 times). Tighter cap.
        RateLimiter::for('legal-hold', function (Request $request) {
            return Limit::perMinute(10)
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });
    }
}
