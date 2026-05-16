# Growth runbook — Phase 34

## Overview

Phase 34 turns the platform from **attributable** (Phase 33's per-
landlord KES cost) into **optimizable** — growth-loop instrumentation
on the revenue + retention side. The unit-economics inputs (cost
denominator + subscription revenue numerator) now both live in the
same observability surface.

Surface map (all Africa/Nairobi onOneServer):

| Cron                                      | Cadence           | Writes / emits                              |
|-------------------------------------------|-------------------|---------------------------------------------|
| `mrr:snapshot`                            | daily 04:05       | `mrr_total_kes`, `mrr_by_plan_kes`          |
| `referrals:rollup`                        | daily 04:10       | `landlord_referrals_count_30d`              |
| `engagement:rollup`                       | daily 04:15       | `landlord_engagement_score`                 |
| `churn:audit`                             | weekly Mon 06:00  | `subscription_monthly_churn_rate` + cohort  |
| `subscriptions:trial-ending-reminder`     | daily 09:00       | queues `TrialEndingMailable`                |
| `subscriptions:dunning-emails`            | daily 09:15       | queues `DunningReminderMailable`            |
| `subscriptions:churn-winback`             | daily 09:30       | queues `WinbackMailable`                    |
| `landlords:activation-nudge`              | daily 09:45       | queues `ActivationNudgeMailable`            |

Two new alert keys: `high_churn_rate` (sev2), `low_engagement_landlord`
(sev4). One new event: `ReferralAttributed`.

## MRR snapshots

`mrr_snapshots(day, plan_id, mrr_kes, active_subscriptions, new_mrr_kes,
expansion_mrr_kes, contraction_mrr_kes, churned_mrr_kes)` unique on
`(day, plan_id)`. `MrrSnapshotService::snapshotForDate` is idempotent
— re-running for the same day overwrites with the latest computation
(useful for backfills after a plan-rate calibration).

Waterfall meaning per (day, plan):

| Column                  | What it counts                                    |
|-------------------------|---------------------------------------------------|
| `new_mrr_kes`           | MRR from subscriptions whose `created_at` is on D |
| `expansion_mrr_kes`     | Upgrade deltas on D (stubbed in Phase 34 v1)      |
| `contraction_mrr_kes`   | Downgrade deltas on D (stubbed in Phase 34 v1)    |
| `churned_mrr_kes`       | MRR lost from subscriptions cancelled on D        |

**Calibration cadence**: re-run `mrr:snapshot --date=...` for the
prior 30 days whenever `subscription_plans.price_monthly` or
`price_yearly` changes — old snapshots reflect the old price.

## Churn cohorts

`ChurnService::subscriptionCohorts(monthsBack)` returns a triangular
matrix: rows = cohort signup month, columns = months-since-signup.
Algorithm parallels Phase-27 `CohortService::retentionMatrix`, but
keyed on the global subscriptions stream (SaaS retention) instead
of per-landlord leases (tenant retention).

### high_churn_rate playbook (sev2)

1. Pull last 30 days of cancelled subscriptions grouped by
   `cancel_reason`.
2. Split voluntary (`too_expensive`, `missing_features`,
   `switching_competitor`, `business_closing`) from involuntary
   (`technical_issues`, usually failed payment auto-cancel).
3. **Voluntary spike** → bring to product review for prioritisation.
4. **Involuntary spike** → check Paystack webhook health and the
   dunning-email delivery counts. Often points at a card-bin
   issuer issue, not a customer-trust problem.

## Referrals

`users.referral_code` char(8) unique nullable. Auto-assigned by
`UserObserver::created` for landlords via `ReferralAttributionService::
generateCodeFor` (collision-retry up to 5 attempts).

`referrals` ledger: unique on `referred_user_id` (a user can only be
referred once — race winners take all). Status machine:
`pending` → `attributed` (first_invoice milestone) → `rewarded` (manual or
future cron).

### Attribution flow

1. New landlord signs up via referral landing with `?ref=ABC12345`.
2. Frontend posts `POST /referrals/redeem` with the code — service
   writes a `pending` row (validates not self-referral, not already
   referred).
3. Landlord progresses through the onboarding funnel.
4. When `MilestoneRecorded::milestone == first_invoice` fires,
   `AttributeReferralOnMilestone` listener flips pending → attributed
   + dispatches `ReferralAttributed`.

### Fraud guardrails

- Self-referral blocked (referrer.id !== referred.id).
- Duplicate referral blocked (unique constraint on referred_user_id).
- `first_invoice` (not `signed_up`) as the attribution trigger —
  prevents fake-signup farms from earning rewards.

## Engagement scores

Composite 0-100 score per landlord per day stored in
`landlord_engagement_scores(landlord_id, day, score, components json)`.

Weighted formula:

| Weight | Signal           | Source                                |
|--------|------------------|---------------------------------------|
| 30%    | login            | `SecurityLog` EVENT_LOGIN within Nd   |
| 25%    | milestones       | count of 6-step `OnboardingMilestone::FUNNEL` |
| 20%    | usage            | `LandlordUsageMetric` row within 7d   |
| 15%    | property_growth  | `Property` count today vs 30d ago     |
| 10%    | tenant_activity  | `TenantActivity` within 7d on landlord's tenants |

`components` JSON captures per-signal sub-scores so the operator can
diagnose drops (was it login recency? usage drop?).

### low_engagement_landlord intervention playbook (sev4)

Fires only for **paying** landlords below score 30. Free-tier
landlords scoring 0 is often legitimate (monthly-review users).

1. Pull alert metadata for the `landlord_ids` list.
2. For each, inspect components JSON: which signal dropped?
3. login=0 → password-reset spam, or genuinely disengaged → CS call.
4. usage=0 → last activity was 30+ days ago → product issue?
5. tenant_activity=0 → landlord's tenants stopped paying →
   collections problem we can help with.

## Lifecycle campaigns

All four crons use `Cache::add` for idempotency keyed on
(subscription/landlord id + day). Re-running the cron in the same
day is a no-op for already-sent recipients.

| Touch                        | Trigger                                      | Idempotency window |
|------------------------------|----------------------------------------------|--------------------|
| `subscriptions:trial-ending-reminder` | trial_ends_at within +3/+1/0 days   | 2 days             |
| `subscriptions:dunning-emails`        | status=past_due, day-since 1/4/7    | 2 days             |
| `subscriptions:dunning-emails` (cancel) | status=past_due, day-since >= 14  | (action, not email) |
| `subscriptions:churn-winback`         | cancelled_at exactly 7d or 30d ago  | 60 days            |
| `landlords:activation-nudge`          | OnboardingProgress.last_touched_at > 3d AND completed_at IS NULL | 7 days (ISO week)  |

Opt-out integration — Phase 2 follow-up: wire to Phase-28
`NotificationPreference` matrix so landlords can suppress lifecycle
emails without unsubscribing from transactional invoices.

## CI gates

- `Phase34GrowthSurfaceTest` locks down 8 crons + 2 alert keys +
  ReferralAttributed listener + lang/{en,sw}/growth.php parity.
- `Phase24CiTest` parity assertion now includes growth.php — sw key
  ORDER must match en (identity comparison on `array_keys`).
- `runbook:coverage-audit` validates `high_churn_rate` and
  `low_engagement_landlord` resolve to this file.

## Deferrals

Out of scope for Phase 34, candidate Phase 35 follow-ups:

- A/B experiment framework (`config/features.php` expand to registry).
- Metered billing writer wiring into `usage_records` (table already
  exists, no current writer).
- Product analytics SDK (Amplitude/Mixpanel/Heap integration).
- MRR expansion/contraction waterfall computation via `audit_logs`
  diff (Phase 34 ships these columns at 0).
- `NotificationPreference` integration on lifecycle email opt-out.
