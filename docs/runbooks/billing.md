# Billing runbook

Landlord-facing plan management. PropManager's own subscription
revenue surface — distinct from the rent-collection gateways
documented in [payments.md](payments.md).

## Surface ownership

| Subject | Path | Lineage |
|---|---|---|
| `Subscription` | landlord's row in `subscriptions` | Phase 34 GROWTH-CHURN |
| `SubscriptionPlan` | catalogue in `subscription_plans` | Phase 35 PLATFORM |
| `SubscriptionPlanChange` | audit row per /subscription/change | **Phase 60 PLAN-CHANGE** |
| `Coupon` + `CouponRedemption` | `coupons` + `coupon_redemptions` | **Phase 60 COUPONS** |
| `StripeCustomer` | user_id → Stripe Customer id mapping | Phase 42 METHODS |
| Trial machinery | `subscriptions.trial_ends_at` + listener + cron | **Phase 60 TRIAL-DEPTH** |
| Plan feature gates | `subscription_plans` boolean columns + `PlanGateService` | **Phase 60 FEATURE-GATES** |

## Phase 60 PLAN-MANAGEMENT (2026-05-18)

### Plan change (self-serve upgrade/downgrade)

`POST /subscription/change` with `new_plan_id` swaps the landlord's
plan in place. `PlanChangeService::changePlan` wraps
`StripeSubscriptionService::updateSubscription` and writes a
`subscription_plan_changes` audit row regardless of Stripe outcome
(so support can trace user intent vs gateway state). Emits
`PlanChanged` event for downstream listeners (MRR recompute,
notification emails).

Plans.vue routes existing-subscription clicks to `/subscription/change`
rather than `/subscription/subscribe` (which still handles first-time
onboarding to a paid plan).

### Plan gate service

`App\Services\Subscriptions\PlanGateService::can($feature, ?User)`
is the single entry point for plan-feature checks. Source of truth
remains `User::canAccessFeature()` — the service exists for the side
effects (5m cache + denial counter gauge).

| Feature | Plan column |
|---|---|
| `water_billing` | `water_billing_enabled` |
| `ocr` | `ocr_enabled` |
| `reports` | `reports_enabled` |
| `bulk_operations` | `bulk_operations_enabled` |
| `documents` | `document_storage_enabled` |
| `sms` | `sms_notifications_enabled` |

`HandleInertiaRequests::getFeatureAccess` shares the full bundle as
`featureAccess` prop. Vue components read it directly via
`$page.props.featureAccess.reports`. Caretakers gate on their
landlord's plan rather than their own (caretaker User has no
subscription).

The existing `CheckPlanLimits` middleware routes through
PlanGateService so every plan-gated route emits the
`plan_feature_denied_count{feature}` gauge on denial.

### Trial machinery

`TrialStartService::startTrialFor($landlord, $days = 14)` creates a
14-day Trialing subscription on `config('subscriptions.trial_plan_slug',
'starter')`. Auto-fires from `StartTrialOnLandlordRegistered` listener
on the `Illuminate\Auth\Events\Registered` event for `role === 'landlord'`.

Trial countdown on `Subscription/Index.vue`: when `subscription.is_trialing`
shows "Free trial: X days remaining" banner + upgrade CTA.

`trial:auto-expire` cron daily 09:30 Africa/Nairobi (after Phase-34
`TrialEndingReminder` at 09:00) transitions stale trial subscriptions
to `status=Cancelled` with `cancel_reason=trial_expired` + emits
`trial_expired_count` gauge.

### Coupons

`coupons` table (code unique + stripe_coupon_id + discount_type
enum[percent,fixed] + discount_value + max_redemptions + expires_at
+ is_active + soft-deletes). `coupon_redemptions` table with
`unique(coupon_id, user_id)` so same user can't double-redeem.

`CouponService::redeem($code, $user, ?$subscription)` validates
active + within max-redemptions + not previously redeemed by user,
persists redemption row, emits `coupon_redeemed_count{code}` gauge.
Throws `CouponInvalidException` with i18n translation key on
validation failure. The Stripe-side mirror happens via webhook
(Phase 41) — this service writes the local audit record.

`POST /subscription/apply-coupon` with `code` in body. Translation
keys in `lang/{en,sw,ar}/coupons.php`.

### Billing portal

`StripeService::createBillingPortalSession($landlord, $returnUrl)`
calls Stripe BillingPortal Session API + returns the hosted-portal
URL. Throws `BillingPortalUnavailable` on three failure modes:

| Translation key | Reason |
|---|---|
| `billing.portal_not_provisioned` | No StripeCustomer mapping (onboarding incomplete) |
| `billing.portal_gateway_not_configured` | STRIPE_SECRET_KEY missing |
| `billing.portal_session_failed` | SDK call exception |

`POST /subscription/billing/portal` → `SubscriptionController::portal`
calls the service + redirects to the Stripe-hosted URL on success.
Return URL points at `/subscription/index`.

## Cross-references

- Phase 34 GROWTH-CHURN — opened `trial_ends_at` + `TrialEndingReminder`
- Phase 35 PLATFORM — `SubscriptionPlan` catalogue + 8 feature flags
- Phase 40 STRIPE-GATEWAY — `StripeService` + `StripeSubscriptionService`
- Phase 41 GATEWAY-DEEP — webhook handlers (including coupon application on `invoice.payment_succeeded`)
- Phase 42 METHODS — `StripeCustomer` user→stripe_customer_id mapping
- **Phase 60 PLAN-MANAGEMENT** — closes the loop with self-serve UX

## Lineage

Pre-Phase-60: subscription was a one-shot subscribe flow with
cancel/resume. Phase 60 deepens this into a full self-serve plan
management surface that landlords can drive without contacting support.
