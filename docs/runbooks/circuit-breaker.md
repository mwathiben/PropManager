# Circuit Breaker (Phase-16 RESIL-1)

`App\Services\Resilience\CircuitBreaker` is a Cache-backed circuit breaker for outbound HTTP calls to Paystack, M-Pesa, IntaSend, Twilio, Africa's Talking, and the three banking APIs (KCB / Equity / Co-op).

## What it does

Tracks failure counts per `(provider, endpoint)` over a sliding window. When failures cross the threshold, the breaker trips OPEN and subsequent calls fast-fail with `CircuitOpenException` instead of waiting for the full HTTP timeout × retry × backoff budget (which can be 100+ worker-seconds per call against a hard-down provider).

State machine:

```
CLOSED ──(N failures in window)──> OPEN ──(cooldown elapsed)──> HALF_OPEN
                                                                    │
                                       ┌──(success)──> CLOSED ◄─────┤
                                       └──(failure)──> OPEN ◄───────┘
```

## How to enable

Opt-in per provider via env. Defaults to OFF so a misconfigured threshold cannot wedge production.

```bash
# Paystack
PAYSTACK_CIRCUIT_BREAKER_ENABLED=true
PAYSTACK_CIRCUIT_BREAKER_FAILURE_THRESHOLD=5
PAYSTACK_CIRCUIT_BREAKER_FAILURE_WINDOW_SECONDS=60
PAYSTACK_CIRCUIT_BREAKER_COOLDOWN_SECONDS=30
```

Then wire the env → `config/services.php`:

```php
'paystack' => [
    // ... existing config
    'circuit_breaker' => [
        'enabled' => env('PAYSTACK_CIRCUIT_BREAKER_ENABLED', false),
        'failure_threshold' => (int) env('PAYSTACK_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'failure_window_seconds' => (int) env('PAYSTACK_CIRCUIT_BREAKER_FAILURE_WINDOW_SECONDS', 60),
        'cooldown_seconds' => (int) env('PAYSTACK_CIRCUIT_BREAKER_COOLDOWN_SECONDS', 30),
    ],
],
```

## Recommended thresholds

| Provider | Threshold | Window | Cooldown | Notes |
|----------|-----------|--------|----------|-------|
| paystack | 5 | 60s | 30s | Synchronous payment-init/verify; tight cooldown so we re-probe quickly |
| mpesa | 5 | 60s | 60s | M-Pesa daraja has known maintenance windows; longer cooldown |
| intasend | 5 | 60s | 30s | |
| twilio | 10 | 120s | 60s | SMS is bursty; longer window absorbs normal variance |
| africas_talking | 10 | 120s | 60s | |
| kcb / equity / coop | 3 | 300s | 300s | Banking APIs are reconciliation-only (background); aggressive trip is fine |

## Metrics

The breaker emits three Phase-14 counters (visible at `/api/metrics`):

- `circuit_breaker.opened{provider}` — on every CLOSED → OPEN transition
- `circuit_breaker.short_circuited{provider}` — on every call fast-failed while OPEN
- `circuit_breaker.closed{provider}` — on every HALF_OPEN → CLOSED transition

If `circuit_breaker.short_circuited` is climbing without `opened`, an old trip is still in cooldown — investigate and consider `php artisan tinker` + `app(CircuitBreaker::class)->reset($provider, $endpoint)` to force-close.

## When NOT to enable

- **Long-running batch reconciliation** that genuinely needs to attempt every call. The breaker will stop calls during cooldown — the batch will report N failures even if the upstream recovered halfway through. Mitigate by setting a short cooldown (≤ batch-poll-interval).
- **Webhook delivery to operator-controlled endpoints**. Those go through `WebhookDeadLetterService` retry path, which has its own backoff. Double-wrapping doubles the failure-recovery latency.

## Troubleshooting

**"Calls are failing with CircuitOpenException even though the provider is up"**: A prior failure burst tripped the breaker; cooldown has not yet elapsed. `Cache::flush()` clears all breaker state across all providers (nuclear option). Better: `app(CircuitBreaker::class)->reset($provider, $endpoint)`.

**"Breaker is enabled but never trips"**: Check `failure_threshold` vs. actual failure rate. The threshold is a count in the window, not a rate. If the window is 60s and you get 4 failures spaced 20s apart, the count resets via TTL between failures. Lower the threshold or widen the window.

**"Breaker trips on first-call latency spikes"**: Lower the threshold sensitivity OR widen the window. Latency != failure — only exceptions count. If first-call latency causes worker timeout → that's exception → that counts.
