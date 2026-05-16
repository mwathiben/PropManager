# Vendors runbook

Operator reference for the Phase-39 [GROWTH-VENDORS-2] surface:
analytics forwarder, experimentation v2 statistics, push deep-link
contract, archive read path, vendor observability.

## Surface map

| Subsystem | Files | Cron | Alert |
|-----------|-------|------|-------|
| Analytics forwarder | `app/Services/Vendors/PostHogForwarder.php`, `app/Console/Commands/AnalyticsReplayBatch.php`, `config/vendors.php` | `analytics:replay-batch` 04:45 daily | `vendor_flap` sev4 |
| Experimentation v2 | `app/Services/Platform/ExperimentService.php` (chi-square + Bayesian + alpha-spending) | n/a (called on demand by /ops/experiments + UI) | n/a |
| Push deep-link | `app/Services/PushNotificationService.php::send(...,$clickUrl)`, 4 listener migrations | n/a | n/a |
| Archive read path | `app/Console/Commands/ArchiveRehydrate.php`, `app/Services/Archive/ArchiveManifestService.php`, `app/Http/Controllers/Ops/ArchiveSearchController.php`, `resources/js/Pages/Ops/ArchiveSearch.vue` | n/a | n/a |
| Vendor observability | `app/Console/Commands/PushClickThroughAudit.php`, AnalyticsReplayBatch gauges | `push:click-through-audit` 05:10 daily | `vendor_flap` |

## Analytics forwarder

### PostHog setup
Set in `.env`:
```
VENDORS_POSTHOG_ENABLED=true
VENDORS_POSTHOG_API_KEY=phc_xxxxxxxx
VENDORS_POSTHOG_HOST=https://app.posthog.com    # or self-hosted
VENDORS_POSTHOG_SAMPLE_RATE=1.0
```
Run `php artisan config:cache` after change.

### Replay flow
1. `analytics:replay-batch` runs daily 04:45 Africa/Nairobi.
2. Reads `product_events` created after `vendors:analytics:last-replayed-at`
   cache cursor, up to `now() - 5min` (buffer for late writes).
3. Chunks of 100 events flow through
   `AnalyticsForwarderInterface::flush()`.
4. Cursor advances ONLY when `retryable === 0` for the chunk — preserves
   at-least-once semantics on partial failure.

### Manual replay
```
php artisan analytics:replay-batch --chunk=200 --max-runtime-seconds=300
```

### Flap playbook (vendor_flap sev4)

Triggers when error rate > 10% AND attempted >= 10 in the current run.

1. Check vendor status page (PostHog: https://status.posthog.com).
2. Look at the last `analytics:replay-batch` line:
   `vendor=posthog accepted=N rejected=N retryable=N error_rate=X.XXXX`.
3. If rejected (4xx, e.g. bad API key) → fix API key, run replay manually.
4. If retryable (5xx) → wait for vendor recovery, no action needed; the
   next run will retry from the same cursor.
5. Alert auto-resolves on the next clean run (resolve via
   `AlertFiringRecorder::resolve('vendor_flap')`).

## Experimentation v2

### Multi-variant χ² test
```
$result = app(ExperimentService::class)->computeChiSquareSignificance(
    'three_arm_cta_color',
    fn(int $userId) => Conversion::where('user_id', $userId)->exists(),
);
// returns {variants, n_per_variant, conversions_per_variant, chi_square, df, p_value, is_significant}
```
Use when ≥3 variants. df = N-1. `is_significant` triggers when p < 0.05.

### Bayesian posterior P(B > A)
```
$result = app(ExperimentService::class)->computeBayesianPosterior(
    'pricing_tier_test',
    $isSuccessCallable,
    50000, // Monte Carlo samples; 10k for fast tests
);
// returns {p_b_better_than_a, expected_lift_pct, ci_95_low, ci_95_high}
```
Prefer this over χ² when product team wants "what's the probability B
is better than A right now" — interpretable without p-value glossing.

### O'Brien-Fleming alpha spending
```
$z = app(ExperimentService::class)->computeAlphaSpendingBoundary(
    peekNumber: 3,
    totalPeeks: 10,
);
// returns z-score boundary; reject H0 only when |observed_z| > $z
```
Use for continuous monitoring (daily peek) without inflating false-positive
rate. Boundary decreases monotonically — final peek approaches z_{0.025} ≈ 1.96.

## Push deep-link contract

### clickUrl parameter
```
$push->send(
    userId: $u->id,
    title: 'New invoice',
    body: 'KES 30,000 due',
    data: null,
    landlordId: $landlordId,
    clickUrl: "/invoices/{$invoice->id}",
);
```
The SW notificationclick handler reads `data.url` and routes
the tab there. Listener-driven paths (lease-renewal, deposit-refund,
alert-firing, weekly-digest) explicitly populate `data.url`.

### iOS Safari install-first FAQ
- iOS 16.4+ supports web push BUT only inside installed PWAs.
- User must tap Share → Add to Home Screen FIRST.
- After install, open the PWA via the home-screen icon (not the
  browser bookmark), then trigger the permission prompt with a
  user gesture (button click).
- Without these steps, `Notification.requestPermission()` silently
  returns 'denied' on iOS.

### /ops/push test runner
- Visit `/ops/push` as `super_admin`.
- Pick recipient, fill title + body + click_url, click Send.
- Flash message confirms delivered/failed with reason.

## Archive read path

### Rehydrate a month
```
php artisan archive:rehydrate --landlord=42 --month=2026-04
# adds rows to rehydrated_product_events with source_path tag
```
Use `--clear-first` to purge previously rehydrated rows for the same path.

### Ops UI
Visit `/ops/archive/search` as `super_admin`. Pick landlord + month,
either to browse already-rehydrated rows or trigger a new rehydrate.

### Manifest cache
`ArchiveManifestService` caches `Storage::disk('archive')->allFiles(...)`
results for 1 hour per landlord. Bust manually via
`ArchiveManifestService::forget($landlordId)` (the controller does this
automatically after a successful rehydrate).

## Observability

### Vendor metrics
- `analytics_events_forwarded_total{vendor}` — accepted events per run.
- `analytics_replay_batch_duration_seconds` — runtime per run.
- `analytics_forwarder_error_rate{vendor}` — (rejected+retryable)/attempted.
- `push_click_through_rate_24h` — clicks / sent, last 24h.
- `push_notifications_sent_24h`, `push_notifications_clicked_24h`.
- `archive_files_written`, `archive_jsonl_bytes_total{landlord_id}` (Phase 37).
- `archive_rows_rehydrated_count{landlord_id, month}`.

### CI gates

`tests/Feature/Vendors/Phase39VendorSurfaceTest.php` asserts:
- Both crons scheduled at the expected cadences.
- `vendor_flap` alert key registered.
- 4 ops routes (`ops.push.show/send`, `ops.archive.show/rehydrate`) exist.
- `lang/{en,sw}/vendors.php` key parity.
- PostHog defaults disabled in `.env.example`.

## Tier-7 cycle 4 = Phase 40 [STRIPE-GATEWAY] handoff notes

Phase 40 should:
- Introduce `PaymentGatewayContract` interface and implement
  `StripeGateway` alongside the existing Paystack flows.
- Map `KES ↔ USD` for international cards (Paystack stays primary
  for KES domestic, Stripe for USD cards).
- Implement Stripe webhook handler with idempotency + replay.
- Build a daily `payments:gateway-reconcile` cron that diffs Stripe
  + Paystack ledgers against `payments` table and fires `gateway_drift`
  alert on mismatch.
- UI: super_admin gateway switcher per landlord (gateway preference).

Operator pivot strategy: gateway selection driven by
`landlord.payment_gateway_preference` enum (paystack | stripe | auto).
Auto means: Paystack if KES, Stripe if USD/EUR/GBP.
