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

---

## Phase 56 — GROWTH-ATTRIB-1 (2026-05-18)

Deepens Phase 34/35/39 growth + experimentation primitives. Closeout: see `phase-56-audit-prd.json` for the 18 findings (5H/9M/4L).

### MULTI-TOUCH attribution

`attribution_touchpoints` table records every channel a user passed through before converting (referral / organic_search / paid_search / social / email / direct / invitation). `AttributionTouchpointRecorder::record` is idempotent (1-second window dedupe) + fail-soft (log + swallow so registration never 500s).

`App\Services\Growth\AttributionModelService::computeForUser($userId, $convertedAt)` returns `[model_name => [channel => credit_pct]]` for all four models:

- `first_touch`: 100% to the earliest touch's channel.
- `last_touch`: 100% to the latest touch's channel.
- `linear`: 100/N distributed equally across every touch.
- `u_shape`: 40% first + 20% spread across middle touches + 40% last. Collapses to 100% for N=1 and 50/50 for N=2.

**Choosing a model** (decision matrix):

| Scenario | Use |
|---|---|
| Short journey, single dominant channel (e.g., paid search → purchase) | `last_touch` |
| Long awareness funnel (organic discovery weeks before purchase) | `first_touch` |
| Multi-channel journey, want even visibility | `linear` |
| You believe entry + exit channels matter most | `u_shape` |

RegisteredUserController records the registration touchpoint at the commit boundary with channel inferred from session context (invitation / referral / direct).

### FUNNEL-SANKEY

Canonical funnel stages live in `App\Services\Growth\FunnelStage` (backed enum: SIGNUP / ONBOARDING_COMPLETE / FIRST_PAYMENT / RETAINED_60D). Emit via `FunnelEventEmitter::emit(User, FunnelStage)` which wraps `ProductEventTracker::track` with the `funnel.<stage>` naming the rollup queries on.

`FunnelRollupService::computeSankeyPayload(?landlordId, $days = 90)` returns a balanced Sankey shape: continuation links between adjacent stages plus synthetic `dropped_at_<next_stage>` nodes so totals reconcile at every boundary. `landlordId === null` is ops mode (all landlords via `withoutGlobalScopes`); a specific id scopes to one landlord's funnel.

`resources/js/Components/Growth/FunnelSankey.vue` is hand-rolled — no `d3-sankey`, no library dependency. SVG `<rect>` nodes + cubic Bezier `<path>` links. WCAG AA contrast on continuation (emerald) vs drop-off (gray) link colors.

### COHORT-BY-SOURCE

`users.acquisition_source` enum (`organic` | `referral` | `paid` | `invitation` | `unknown`) is stamped at the registration commit boundary based on the same channel context the multi-touch recorder uses. Backfill heuristic: existing rows get `invitation` when invitations.accepted_at matches the user email, then `referral` when referrals.referred_user_id matches, else `unknown`.

`ChurnService::cohortsBySource(int $monthsBack = 12)` returns `[{cohort_month, source, size, retention}]` where `retention[m]` = fraction of the cohort with any product_events row inside calendar month m relative to the cohort_month. Activity-based (not subscription-based) so the curve answers "are users still using the product?" rather than "are they still paying?"

### AB-AUTO-PROMOTE

`experiments:auto-promote` runs nightly at 03:30 Africa/Nairobi and flips RUNNING experiments to CONCLUDED when the dual significance gate passes:

- `chi-square p_value < 0.01` (variants are genuinely different)
- `bayes p_b_better_than_a > 0.95` OR `< 0.05` (one variant wins definitively)

`experiments.success_event_name` (nullable) lets each experiment name its own conversion event. NULL keeps Phase 39's default behaviour (any product_event after exposure.fired_at). When set, only that exact event_name counts toward the conversion rate.

Multi-arm experiments skip auto-promotion because `computeBayesianPosterior` requires exactly 2 variants — operator manually concludes those via `/ops/experiments/{experiment}/conclude`.

`ExperimentConcluded` event fires on promotion; `LogExperimentConclusion` listener writes a `product_events` row 'experiment.concluded' with `[experiment_key, winning_variant_key, chi_p, bayes_posterior]` so the operator timeline shows every flip.

**Manual override**: an operator can transition a RUNNING experiment to CONCLUDED via `ExperimentController::conclude` at any time — the cron only auto-promotes when its gate passes, never blocks the manual path.

### OPS-GROWTH-DASHBOARDS

`/ops/growth/attribution` is the super-admin landing page surfacing all four analyses in a 2x2 grid:

| Card | Source |
|---|---|
| Attribution models (last 30d) | `AttributionModelService::computeForUser` aggregated across recent touchpoint users |
| Funnel (last 90d) | `FunnelRollupService::computeSankeyPayload(null, 90)` |
| Cohort retention by source | `ChurnService::cohortsBySource(6)` |
| Auto-promoted experiments | `Experiment::CONCLUDED` joined with `product_events` 'experiment.concluded' for chi_p / bayes_posterior |

### CI surfaces

`tests/Feature/Growth/Phase56GrowthAttribSurfaceTest` cross-category presence map; per-category behavioural tests in sibling Phase56* files.

Cross-references: [Phase 34 referral lineage](#phase-34--growth-mvr) | [Phase 35 experiments + product_events lineage](#phase-35) | [Phase 39 chi-square + Bayesian lineage](#phase-39).
