# Platform runbook — Phase 35

## Overview

Phase 35 closes the platform-infrastructure debt that Phase 34's
growth-loop work surfaced. Phase 34 measured outcomes (MRR, churn,
engagement); Phase 35 shipped the mechanisms underneath:

- A/B experiments framework
- Metered billing writer (UsageRecord finally has callers)
- Append-only product analytics event stream
- Subscription proration + scheduled downgrades + MRR waterfall fix
- NotificationPreference integration on lifecycle Mailables

Surface map (all Africa/Nairobi onOneServer):

| Cron                                       | Cadence            | Writes / emits                              |
|--------------------------------------------|--------------------|---------------------------------------------|
| `subscriptions:apply-downgrades`           | daily 02:00        | flips plan_id at scheduled period boundary  |
| `metered:soft-cap-audit`                   | daily 04:20        | `metered_usage_ratio` + overage alert       |
| `product:rollup`                           | daily 04:25        | `product_event_count_24h{event=X}`          |
| `notifications:preference-drift-audit`     | weekly Sun 07:00   | `notification_preference_drift_count`       |

One new alert key: `high_metered_overage` (sev4).

## Experiments (PLATFORM-EXP)

`experiments` table holds declarative variant definitions. Lifecycle:

```
draft → running → paused → concluded
        ↑__________|
```

`ExperimentService::variantFor($user, $key)` returns the assigned
variant. Sticky — same user always sees same variant within an
experiment, even if weights change mid-flight. The variant is frozen
at the moment of first `variantFor` call by the
`experiment_exposures` unique constraint on (user_id, experiment_key).

### Adding an experiment

1. Insert a row into `experiments` with status='draft' + variants
   array of `[{key, weight}]` (weights sum to 100).
2. Flip status to 'running' when ready to dispatch.
3. Frontend reads `page.props.experiments[experiment_key]` for the
   user's assigned variant — Inertia share is cached 60s.
4. Once concluded, set `winning_variant_key` and status='concluded'
   — variantFor returns the winner unconditionally (rollout).

### Concluding without a winner

If the experiment was abandoned (no clear winner, dropped feature),
set status='paused'. variantFor returns 'control' for paused
experiments — production traffic gets the safe baseline.

## Metered billing (PLATFORM-METER)

`UsageRecord` (committed 2025-12-27) finally has production writers.
The Phase-35 wiring:

1. **CheckPlanLimits middleware terminate()** fires after the
   response is sent for WRITE-class requests (POST/PUT/PATCH/DELETE)
   with `plan:FEATURE` middleware on the route. Calls
   `MeteredUsageRecorder::record($user, $feature, 1)`.
2. **`metered:soft-cap-audit`** walks paying landlords × billable
   features (properties/units/caretakers/buildings), computes
   ratio = usage / limit, emits `metered_usage_ratio{feature,
   plan_slug}` gauge for top 50.
3. **`high_metered_overage` alert** fires at ratio > 1.5 for any
   paying landlord.

### Overage outreach playbook (high_metered_overage, sev4)

1. Pull the alert metadata `offenders` array for landlord_id +
   feature + ratio.
2. ratio 1.5-2.0 → automated email nudge ("we noticed you're
   doing N properties — Pro tier supports unlimited").
3. ratio > 2.0 → CS outreach for upgrade conversation. Mention
   the specific feature ratios; "you're at 2.5x on properties
   and 1.8x on units" lands better than "consider upgrading".

## Product analytics (PLATFORM-ANALYTICS)

`product_events` table: append-only, immutable rows. Schema:

```
id, user_id, landlord_id, event_name, properties json, created_at
```

`landlord_id` auto-resolves: landlord = own id, tenant/caretaker =
landlord_id, guest = null. TenantScope auto-filters reads.

### Adding an event

1. Call `ProductEventTracker::track($name, $properties, $user)`
   from anywhere a meaningful product action happens.
2. Event name is free-form but use snake_case, past tense:
   `signup_completed`, `invoice_issued`, `plan_upgraded`.
3. Properties are JSON — keep keys stable for downstream queries.

### Sample rate

`config/platform.analytics_sample_rate` defaults to 0.1 (10% of
requests fire the auto-page_view event). Set to 1.0 in development.
Set to 0.0 to disable auto-tracking entirely.

Manual `track()` calls always fire — only the middleware auto-emit
is sampled.

## Billing (PLATFORM-BILLING)

### Proration formula

For an upgrade mid-cycle:

```
prorated_amount_kes = (new.price_monthly - old.price_monthly)
                    × (remaining_days / total_days)
```

Worked example: Starter (KES 1500) → Pro (KES 5000), 20 days
remaining of a 30-day cycle:

```
prorated = (5000 - 1500) × (20 / 30) = 3500 × 0.667 ≈ 2333.33
```

Downgrades default to 0 — customer keeps the higher tier until
period end.

### Scheduled downgrade flow

```
SubscriptionService::scheduleDowngradeAtPeriodEnd
  → writes subscription_changes row with scheduled_for=period_end,
    effective_at=NULL
  → plan_id stays on the OLD tier until the cron fires
subscriptions:apply-downgrades cron (daily 02:00)
  → finds rows where scheduled_for <= now AND effective_at IS NULL
  → flips plan_id + stamps effective_at
```

### MRR waterfall (expansion + contraction)

Phase 34 shipped `mrr_snapshots.expansion_mrr_kes` and
`contraction_mrr_kes` at 0. Phase 35 PLATFORM-BILLING-3 reads
`subscription_changes` on day D where `effective_at` is within
the day boundary:

- `upgrade` change INTO plan P → expansion (sum to_price - from_price)
- `downgrade` change AWAY from plan P → contraction (sum from_price - to_price)

Both columns stored as positive numbers.

## Notification preferences (PLATFORM-NOTIF)

Phase-28 NotificationPreference matrix extended with
`lifecycle_enabled` column (default true — opt-in). The 4 Phase-34
lifecycle Mailables now gate on `LifecycleOptInChecker::allows(user)`:

| Mailable                      | Gate                                  |
|-------------------------------|---------------------------------------|
| `TrialEndingMailable`         | lifecycle_enabled AND email_enabled  |
| `DunningReminderMailable`     | lifecycle_enabled AND email_enabled  |
| `WinbackMailable`             | lifecycle_enabled AND email_enabled  |
| `ActivationNudgeMailable`     | lifecycle_enabled AND email_enabled  |

### Transactional-locked types

`invoice` + `receipt` are transactional types. POST
`/api/notifications/preferences` with `{type:invoice, enabled:false}`
returns 422 `transactional_locked`. The drift audit catches
historical rows where these somehow ended up false.

### Self-serve endpoint

```
POST /api/notifications/preferences
Body: { type, channel?, enabled }
```

- `type` alone toggles the type-level flag (e.g. all lifecycle off).
- `type + channel` toggles a channel-level flag (e.g. SMS off).
- `enabled=false` on a transactional type returns 422.

## CI gates

- `Phase35PlatformSurfaceTest` locks down 4 crons (apply-downgrades,
  soft-cap-audit, product-rollup, drift-audit) + 1 alert key
  (high_metered_overage) + lang parity.
- `Phase24CiTest` parity assertion now includes platform.php — sw
  key ORDER must match en.
- `runbook:coverage-audit` validates `high_metered_overage`
  resolves to this file.

## Deferrals (out of scope for Phase 35)

- Real Paystack proration call (gateway-specific work, Phase 36).
- Experiment statistical significance computation (operator reads
  raw counts; tools like Optimizely do this for you if needed).
- Amplitude/Mixpanel/Heap SDK integration (replay product_events
  rows in batches).
- Per-landlord product event retention policy (currently keeps
  everything).
