# PWA-depth runbook — Phase 37

## Overview

Phase 37 closes the FE/gateway/retention/admin debt that 35 phases of
backend-heavy work accumulated. Three planes:

1. **Frontend activation** — `useWebPush` composable + Settings/
   Notifications.vue + Pages/Ops/Experiments/{Index,Show}.vue make
   previously dark Phase-26 push backend, Phase-35 notification
   preferences and Phase-35 experiments usable from the UI.
2. **Gateway sync** — Paystack subscription PUT/disable/sync wired
   into `SubscriptionService::changePlan` plus a nightly
   reconciliation cron so DB and Paystack don't diverge.
3. **Retention + statistics** — `product:prune` weekly + monthly
   `product:cold-storage-rollover` cap `product_events` growth;
   `ExperimentService::computeSignificance` two-proportion z-test
   stops eyeballed experiment conclusions.

## Surface map

| Surface | Path | Audience | Auth |
| --- | --- | --- | --- |
| Notification preferences page | `/settings/notifications` | landlord | session |
| Experiments admin list | `/ops/experiments` | super_admin | session |
| Experiments admin detail | `/ops/experiments/{id}` | super_admin | session |
| Push subscribe | `POST /notifications/push/subscribe` | authenticated | session + CSRF |
| Push unsubscribe | `POST /notifications/push/unsubscribe` | authenticated | session + CSRF |
| VAPID public key | `GET /notifications/push/key` | authenticated | session |
| Paystack subscription webhook | `POST /webhooks/v2/paystack` (subscription.* events) | gateway | HMAC-SHA512 |

## Push notifications

The `useWebPush()` composable wraps the pre-existing
`usePushNotifications()` (Phase 26) with VAPID key auto-fetch and
rotation-detection. Subscribe flow:

```
useWebPush.subscribe()
  → GET /notifications/push/key  (in-memory 60s cache)
  → Notification.requestPermission()
  → registration.pushManager.subscribe({applicationServerKey})
  → POST /notifications/push/subscribe {endpoint, keys}
```

### VAPID rotation playbook

When you regenerate VAPID keys (`PushNotificationService::
generateVapidKeys + saveVapidKeys`), every existing subscription
is invalidated by the browser vendor. `useWebPush.refreshKey()`
detects the delta against the cached `lastSubscribedKey`, calls
`unsubscribe()` + `subscribe()` automatically. Operators do NOT
need to push a global re-subscribe message.

### iOS Safari push: install-first prerequisite (Phase 39)

iOS 16.4+ Safari supports web push but enforces THREE
prerequisites:

1. **iOS 16.4 or newer** — older versions silently lack the
   PushManager API. `useWebPush.isSupported` returns false.
2. **PWA installed via Share → Add to Home Screen** — Safari
   does NOT prompt for notification permission when the page is
   open as a regular tab. The user MUST open PropManager from
   the Home Screen icon (display-mode: standalone).
3. **Same-domain user gesture** — after install, opening the
   PWA the FIRST time + tapping a button (any click anywhere on
   the page) unlocks `Notification.requestPermission()`. Without
   the gesture the call resolves to 'denied' immediately.

**Landlord-facing FAQ entry** (link from Settings/Notifications.vue's
push card when iOS device detected): "On iPhone/iPad, tap the
Share button in Safari → 'Add to Home Screen' → open PropManager
from your Home Screen → then enable push notifications from
Settings."

Phase-39 PUSH-EXTEND-2 plans for an inline helper banner in
Settings/Notifications.vue that checks
`/iPhone|iPad/.test(navigator.userAgent) && !window.matchMedia('
(display-mode: standalone)').matches` and surfaces the install
instruction before the subscribe button.

### Browser-side push test runner (Phase 39 PUSH-EXTEND-3)

`/ops/push` (super_admin only) renders a form for manual end-to-
end push validation: pick recipient user, type title + body +
clickUrl, hit Send. The controller calls
`PushNotificationService::send(..., clickUrl: $clickUrl)` and
returns a result line with the delivered-to-N-subscriptions
count. Replaces tinker-script-based testing for incident
debugging.

## Insight weekly digest

`insight:weekly-digest` cron runs Mon 07:00 Africa/Nairobi
(slots after Phase-32 mttr:audit 06:45 and Phase-34 churn:audit
06:00). For every paying landlord:

1. Cache::add(`insight:digest:{id}:{iso_week}`, 8d) idempotency
   short-circuits double-send.
2. `LifecycleOptInChecker::allows()` gate — false → skip
   (counted in `insight_digest_skipped_optin_count`).
3. `InsightDashboardService::landlordSummary($id)` → null
   → skip (counted in `insight_digest_skipped_no_summary_count`).
4. Queue `WeeklyInsightDigestMailable($landlord, $summary)`.

Counters: `insight_digest_sent_count`, `insight_digest_skipped_
optin_count`, `insight_digest_skipped_no_summary_count`.

### Digest delivery silence playbook

If sent_count = 0 for an entire week:

1. `php artisan insight:weekly-digest --dry-run` — verifies
   landlords are reachable.
2. Check `LifecycleOptInChecker` query — if every preference row
   has `lifecycle_enabled = false`, surface the bulk opt-out as
   an incident.
3. Verify `InsightDashboardService::landlordSummary` is returning
   non-null — check `MrrSnapshot` / `LandlordEngagementScore`
   freshness.

## Paystack proration sync

`SubscriptionService::changePlan` writes the local
`SubscriptionChange` audit row + flips `plan_id` under
`DB::transaction`. Then for UPGRADE with `subscription.
paystack_subscription_code` AND `plan.paystack_plan_code`
both set, calls `PaystackSubscriptionService::updateSubscription`
and stores the structured response on `gateway_response`.

Gateway failures are swallowed here — the user-facing operation
stays atomic on the DB side. The nightly reconciliation cron
`gateway:proration-audit` (daily 05:30) catches drift.

### `high_gateway_proration_drift` sev3 playbook

Threshold: 5+ unreconciled rows in 24h.

1. `php artisan gateway:proration-audit` — manual reconciliation
   pass. Output reports per-row outcome.
2. Inspect the `gateway_response` column on
   `subscription_changes` — look for repeated `http_status: 404`
   (subscription_code drift) vs `http_status: 401` (secret
   rotation) vs `http_status: 0` (network failure).
3. For 404s: the landlord cancelled on Paystack-side; sync via
   `php artisan tinker` →
   `PaystackSubscriptionService::syncFromGateway($code)` and
   update local Subscription manually.
4. For 401s: rotate `PAYSTACK_SECRET_KEY` and restart workers.
5. For network failures: check `php artisan health:dependencies`
   — Paystack may be in degraded state.

The alert auto-resolves once `subscription_proration_drift_count_
24h` gauge drops below threshold.

## Experiments admin

`/ops/experiments` super_admin CRUD replaces raw-SQL experiment
lifecycle work. State machine: draft → running ↔ paused →
concluded (with `winning_variant_key`).

### `ExperimentService::computeSignificance` usage

```php
$result = $service->computeSignificance(
    'onboarding_cta_color',
    fn (int $userId) => User::find($userId)?->subscription?->status === 'active',
);
// $result['z_score'], $result['p_value'], $result['is_significant']
```

Two-proportion pooled z-test against α=0.05. Throws when
experiment has != 2 variants — multi-variant + Bayesian land in
Phase 38+ candidates.

Interpretation guide:

| `z_score` | `p_value` | Action |
| --- | --- | --- |
| `< -1.96` | `< 0.05` | variant A significantly worse — pick B |
| `> 1.96` | `< 0.05` | variant A significantly better — pick A |
| `\|z\| < 1.96` | `>= 0.05` | inconclusive — extend exposure or pick by preference |

## Retention + cold storage

`product:prune` (weekly Sun 03:00) deletes events older than
`config('platform.product_events_retention_days')` in chunks.
`product:cold-storage-rollover` (monthly 1st 03:30) writes the
previous calendar month's events to `Storage::disk('archive')`
as gzipped JSONL before deletion.

### Archive shape

```
product-events/{landlord_id}/{YYYY-MM}/events.jsonl.gz
```

Each line is a JSON object:
```json
{"id": 123, "user_id": 4, "landlord_id": 4, "event_name": "page_view",
 "properties": {"path": "/dashboard"}, "created_at": "2026-04-02T10:23:45+00:00"}
```

In production, set `ARCHIVE_DISK_DRIVER=s3` and `ARCHIVE_BUCKET=
propmanager-archive` (or your bucket). S3 LIFECYCLE rule should
transition >365 day objects to Glacier Deep Archive.

### Retention prune lag playbook

If `product_events_pruned_count = 0` for multiple weeks AND row
count keeps climbing:

1. Check `php artisan product:prune --dry-run` — verifies the
   delete query is matching rows.
2. If matching but DELETE silently failing: check
   `query_count_24h` gauge during prune window for lock waits.
3. Increase `--chunk` to 10000 only if MySQL `innodb_lock_wait_
   timeout` is generous enough; otherwise leave at 5000.

## CI gates

- `Phase37PushSubscriptionTest` — backend contract useWebPush relies on.
- `Phase37WeeklyDigestTest` — cron + opt-in + idempotency.
- `Phase37GatewayTest` — changePlan + webhook + drift reconciliation.
- `Phase37FrontendAdminTest` — settings page + experiments admin.
- `Phase37RetentionStatsTest` — prune + cold storage + significance.
- `Phase37PwaDepthSurfaceTest` — 4 cron registrations + 1 alert + 5 route names + lang parity.

## Deferrals (Phase 38+ candidates)

Explicit punts, not gaps:

- **Amplitude/Mixpanel/Heap SDK integration** — `AnalyticsForwarder`
  + batched `product_events` replay. Foundation is in place; vendor
  commitment is the blocker.
- **Stripe gateway parity** — Kenya market is Paystack-first.
- **Bayesian sequential analysis** for experiments — z-test is
  sufficient for 2-variant boolean conversions.
- **Multi-variant significance** — 3+ variants need ANOVA/χ², not
  the 2-proportion z-test shipped here.
- **iOS Safari push** — works on iOS 16.4+ PWAs only; document in
  user-facing FAQ.
- **Browser-side push test runner** under `/ops/push` — the manual
  send-push admin endpoint is sufficient for ops smoke tests.
- **Historical cold-storage rehydration** — read path against S3
  Glacier. No current use case; Athena query against the JSONL.gz
  files covers most operator needs.
