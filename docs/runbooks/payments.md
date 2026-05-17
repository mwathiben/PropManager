# Payments runbook

Operator reference for the Phase-40 [STRIPE-GATEWAY] payment-rail
surface: PaymentGatewayInterface contract, Paystack + M-Pesa + Stripe
implementations, reconciliation, currency routing, per-landlord
preferences.

## Per-landlord vs system-wide gateway credentials

PropManager has TWO distinct credential planes for payment gateways
and confusing them leaks credentials cross-tenant OR breaks SaaS
billing. Each gateway has BOTH a per-landlord and a system-wide
incarnation.

### Per-landlord credentials — tenant rent collection

Each landlord holds their own Paystack/Stripe/M-Pesa account.
Credentials live on the `payment_configurations` table (encrypted
columns: `paystack_secret_key`, `stripe_secret_key`,
`stripe_webhook_secret`, `mpesa_consumer_secret`, etc.).

| Service | Loads credentials via |
|---------|----------------------|
| `PaystackService` | `PaymentConfiguration` injected via constructor or `withConfig()` |
| `StripeService` | `PaymentConfiguration` injected via constructor or `withConfig()` |
| `MpesaService` | `PaymentConfiguration` injected via constructor or `withConfig()` |

When tenant pays rent → PropManager forwards to landlord's own gateway
account → settles to landlord's bank.

### System-wide credentials — PropManager's own SaaS billing

PropManager itself collects subscription fees from landlords. Those
flow into PropManager's central Paystack/Stripe account. Credentials
live in `config/services.php` (read from `.env`):

```
STRIPE_PUBLISHABLE_KEY=pk_live_xxx
STRIPE_SECRET_KEY=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

| Service | Loads credentials via |
|---------|----------------------|
| `PaystackSubscriptionService` | `Setting::getSystem(...)` |
| `StripeSubscriptionService` | `Setting::getSystem(...)` |
| `StripeWebhookController` | `config('services.stripe.webhook_secret')` |

When landlord pays their monthly PropManager subscription → flows to
PropManager's own gateway account → settles to PropManager's bank.

### Rule of thumb

- Money flowing **to** a landlord → per-landlord credentials.
- Money flowing **to** PropManager → system-wide credentials.
- Mixing them is a Phase-40 [GATEWAY-CONTRACT-3] violation.

## Currency routing

Default routing (Phase 40 [GATEWAY-CURRENCY-3]):

| Currency | Gateway |
|----------|---------|
| KES | Paystack (domestic) |
| USD, EUR, GBP | Stripe |

```php
$gateway = app(PaymentGatewayManager::class)->routeFor(Currency::USD);  // → Stripe
$gateway = app(PaymentGatewayManager::class)->routeFor(Currency::KES);  // → Paystack
```

Override per-landlord via `users.payment_gateway_preference`
enum (paystack | stripe | auto). `auto` means: follow the table above.

```php
// auto → falls back to currency routing
// paystack/stripe → forced regardless of currency
$gateway = app(PaymentGatewayManager::class)->routeForUser($landlord, Currency::USD);
```

Operator override via `/admin/gateways` (super_admin only) — pick
per-landlord preference from a dropdown. Use for support cases:
"landlord X needs their next transaction on Stripe regardless of
currency rule."

## Gateway slot inventory

```
PaymentGatewayManager::supportedGateways() = ['paystack', 'mpesa', 'stripe']
PaymentGatewayManager::gateway($name): PaymentGatewayInterface
PaymentGatewayManager::paystack(): PaystackGateway
PaymentGatewayManager::mpesa(): MpesaGateway
PaymentGatewayManager::stripe(): StripeGateway
PaymentGatewayManager::available(): array<string, PaymentGatewayInterface> (filters by isConfigured())
PaymentGatewayManager::routeFor(Currency $currency): PaymentGatewayInterface
PaymentGatewayManager::routeForUser(User, Currency): PaymentGatewayInterface
```

## Stripe webhook configuration

1. In Stripe Dashboard → Developers → Webhooks → Add endpoint.
2. URL: `https://YOUR-DOMAIN/webhooks/v2/stripe`
3. Events to listen for:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `payment_intent.succeeded` (future use)
   - `charge.refunded` (future use)
4. Copy the signing secret → set `STRIPE_WEBHOOK_SECRET` env var.
5. `php artisan config:cache`.

### Webhook response taxonomy

| Status | Meaning |
|--------|---------|
| 200 `{status: accepted}` | Signature valid + processed |
| 200 `{status: duplicate}` | Already processed (within 24h dedup window) |
| 401 `{error: invalid_signature}` | Signature mismatch |
| 422 `{error: invalid_payload}` | Body unparseable |
| 503 `{error: not_configured}` | STRIPE_WEBHOOK_SECRET missing |

## Reconciliation

```
php artisan payments:gateway-reconcile [--gateway=stripe] [--landlord=42]
```

Runs daily 05:45 Africa/Nairobi. For each landlord × each configured
gateway, compares local `payments` table against remote ledger,
emits:

- `gateway_reconcile_drift_count{gateway,landlord_id}` (per landlord)
- `gateway_reconcile_drift_total{gateway}` (per gateway aggregate)

### gateway_drift sev3 alert playbook

Fires when any gateway's total drift count > 5 over 24h.

1. Identify which gateway: `payments:gateway-reconcile --gateway=stripe`
2. Identify which landlord(s): inspect `gateway_reconcile_drift_count{landlord_id}` gauge.
3. Run reconcile for that landlord: `--landlord=42`.
4. Examine discrepancies — likely causes:
   - Webhook delivery failure → events not landing locally. Check Stripe Dashboard → Events → Recent Deliveries.
   - Local payment created but no remote charge → user retry duplicated a row. Investigate `payments` for `paystack_reference` / `stripe_reference` collisions.
   - Refund processed on gateway but local `is_voided` not flipped → manual fix or wait for refund webhook.
5. Alert auto-resolves on the next clean run.

## /admin/gateways operator UI

Super_admin only. Shows every landlord with:
- Paystack config-state dot (green = configured + enabled)
- Stripe config-state dot
- Preference select (auto / paystack / stripe)

Use cases:
- Forcing a landlord onto a specific gateway during support.
- Bulk-audit which landlords have which gateways enabled.

## Observability

| Gauge | Source | Purpose |
|-------|--------|---------|
| `gateway_reconcile_drift_count{gateway,landlord_id}` | `payments:gateway-reconcile` | Per-landlord drift |
| `gateway_reconcile_drift_total{gateway}` | same | Per-gateway aggregate |
| `subscription_proration_drift_count_24h` | Phase-37 `gateway:proration-audit` | UPGRADE row drift |

## CI gates

`tests/Feature/Gateway/Phase40GatewaySurfaceTest.php` asserts:
- PaymentMethod enum has Stripe + supportedGateways lists 'stripe'.
- payment_configurations / subscription_plans / subscriptions / users have the new columns.
- `webhooks.v2.stripe` + `admin.gateways.{index,update}` routes exist.
- `payments:gateway-reconcile` cron scheduled at the expected cadence.
- `gateway_drift` alert key registered.
- `lang/{en,sw}/payments.php` parity.
- Stripe PHP SDK installed (`Stripe\StripeClient` + `Stripe\Webhook`).

## Stripe webhook event coverage (Phase 41 GATEWAY-WEBHOOK-DEEP)

| Event | Handler | Side effect |
|-------|---------|-------------|
| `customer.subscription.created` | `handleSubscriptionEvent` | Subscription.status = active (Phase 40) |
| `customer.subscription.updated` | `handleSubscriptionEvent` | Subscription.status = mapped from Stripe (Phase 40) |
| `customer.subscription.deleted` | `handleSubscriptionEvent` | Subscription.status = cancelled + cancelled_at (Phase 40) |
| `payment_intent.succeeded` | `handlePaymentIntentSucceeded` | Idempotent Payment row creation (lands when `landlord_id` + `lease_id` in metadata); skips for SaaS-billing intents |
| `charge.refunded` | `handleChargeRefunded` | Payment.is_voided = true + dispatches PaymentRefundedExternal event |
| `invoice.payment_failed` | `handleInvoicePaymentFailed` | Subscription.status = past_due (Phase-34 dunning picks it up) |
| `charge.dispute.created` | `handleChargeDisputeCreated` | OperationalIncident sev3 row with affected_services=[stripe, payments] |
| `account.updated` | `handleAccountUpdated` | StripeConnectService::syncAccountStatus refreshes PaymentConfiguration.stripe_connect_* columns |
| `price.updated` | `handlePriceUpdated` | Detects Stripe-side Price drift vs SubscriptionPlan.price_monthly; emits subscription_plan_drift gauge |

All handlers dedup on `event.id` via 24h `Cache::add` — duplicate
deliveries return `{status: duplicate}` without re-executing.

## Stripe Connect Express onboarding flow (Phase 41 GATEWAY-CONNECT)

For landlords accepting USD/EUR/GBP via Stripe:

1. Super_admin invokes `StripeConnectService::createExpressAccount($landlord, $country)`.
   Returns `{success: true, account_id: acct_xxx, status: pending_onboarding}`.
2. Service persists `acct_xxx` → `payment_configurations.stripe_connect_account_id`
   (encrypted) with status `pending_onboarding`.
3. UI generates hosted onboarding URL via
   `onboardingLink($accountId, $returnUrl, $refreshUrl)`. Landlord
   completes KYC + bank details on Stripe-hosted page.
4. Stripe posts `account.updated` webhook → handler calls
   `syncAccountStatus` → updates `stripe_connect_status`
   (`pending_onboarding` → `pending_verification` → `active`) +
   `stripe_connect_charges_enabled` + `stripe_connect_payouts_enabled`.

`isConfigured()` checks `secret_key` only — Connect onboarding is
gated separately by `stripe_connect_charges_enabled`.

## Checkout routing (Phase 41 GATEWAY-CHECKOUT-2)

Endpoint: `POST /invoices/{invoice}/checkout/initialize`

Routing decision (in order):

| Landlord preference | Invoice currency | Gateway picked |
|---------------------|------------------|----------------|
| `paystack` (forced) | any              | Paystack       |
| `stripe` (forced)   | any              | Stripe         |
| `auto`              | KES              | Paystack       |
| `auto`              | USD/EUR/GBP      | Stripe         |

Response envelope shape (both gateways):

```json
{
  "status": "success",
  "gateway": "stripe" | "paystack",
  "data": {
    "reference": "pi_... | INV_...",
    "authorization_url": "...",  // Paystack hosted-checkout URL
    "client_secret": "pi_..._secret_..."  // Stripe stripe.js confirm flow
  }
}
```

Frontend renders the appropriate flow based on `gateway` field.

## Plan-sync drift playbook (Phase 41 GATEWAY-PLAN-SYNC)

`stripe:plan-sync` runs weekly Mon 04:35 Africa/Nairobi — pushes
active `SubscriptionPlan` rows to Stripe Prices and writes the new
`price.id` to `plan.stripe_plan_code`.

Stripe-side edits (operator changes a Price in the Stripe Dashboard
for support reasons) trigger `price.updated` webhook →
`subscription_plan_drift` gauge fires.

**When the gauge fires:**

1. Inspect `subscription_plan_drift{plan_id}` in the ops dashboard.
2. Decide which side wins:
   - **App wins** (default): run `php artisan stripe:plan-sync` to
     overwrite the Stripe Price with the app's value.
   - **Stripe wins** (rare — usually only for one-off support pricing):
     manually update `SubscriptionPlan.price_monthly` to match Stripe.
3. Gauge value should return to 0 on next `price.updated` after
   resolution.

Auto-resolution is intentionally NOT shipped — pricing decisions
involve revenue, manual review is the right default.

## Phase 42 [PAYMENTS-INTL] handoff notes

Phase 41 closed the Phase-40 deferral list. Phase 42's surface
candidates from the Stripe + cross-border surface:

- **Tax/VAT line items on PaymentIntent**: Kenyan VAT registry
  integration + Stripe Tax for international charges. Material for a
  full cycle (KRA iTax integration alone is non-trivial).
- **Full bidirectional plan sync**: `price.updated` webhook drift
  detection lands in Phase 41. Phase 42 candidate: opt-in
  auto-resolve mode (operator selects "always app-wins" /
  "always stripe-wins" / "manual review" per plan).
- **Stripe Connect Standard**: Express is enough for split-payment
  parity with Paystack subaccounts; Standard adds direct-charge
  capability that landlords with their own Stripe accounts need for
  tax-reporting independence.
- **Multi-currency cart**: each PaymentIntent is single-currency
  today. Cart-style checkout (e.g., tenant pays rent in KES + a
  service add-on priced in USD in the same checkout) deferred.
- **Payment-method save + reuse**: today every checkout is a fresh
  PaymentIntent. Stripe Customers + saved cards for reuse-without-
  re-entering deferred.
- **payouts:stripe-balance-audit cron**: detect Stripe Connect
  payout failures + emit `stripe_payout_failure_count` gauge.
- **Stripe-side Customer ↔ User mapping table**: today the link is
  implicit via metadata.user_id. A first-class table would let
  StripeSubscriptionService::initializeCheckout reuse existing
  Customers (lower friction for returning landlords).

## Phase 42 [PAYMENTS-INTL] TAX

KRA VAT tracking + Stripe Tax opt-in. Phase 42 ships VAT
*tracking* — the actual KRA iTax filing/submission integration is
deferred to Phase 43 (multi-week regulatory work).

Schema:
- `invoice_items.tax_amount_cents` BIGINT UNSIGNED NULL — Money in
  minor units (Phase-17 Money convention). NULL = untaxed line.
- `invoice_items.tax_rate_bps` INT UNSIGNED NULL — basis points
  (1600 = 16% Kenyan statutory VAT).
- `payment_configurations.kra_pin` TEXT (encrypted) — KRA PIN format
  `^[AP]\d{9}[A-Z]$`. `PaymentConfiguration::isVatRegistered()`
  gates on this regex.
- `payment_configurations.vat_rate_bps_override` INT UNSIGNED NULL —
  override the 1600 default per landlord. Use sparingly; KRA
  variations are rare.
- `payment_configurations.stripe_tax_enabled` BOOLEAN DEFAULT false —
  opt-in. Landlords with their own tax filing keep this off.

Service: `App\Services\Tax\StripeTaxService`.
- `computeKenyanVat(Money $subtotal, ?PaymentConfiguration $config)`
  returns Money via bcmath multiply with banker's rounding.
- `vatLineItem(Money, Currency, ?PaymentConfiguration)` returns
  `{description, amount_cents, rate_bps}` for KES; null for non-KES.
- `stripeAutomaticTaxFor(PaymentConfiguration, Currency)` returns
  true only for non-KES with stripe_tax_enabled. Stripe Tax
  doesn't yet cover Kenya — KES always uses local computation.

StripeService::createPaymentIntent appends
`automatic_tax => [enabled => true]` when the landlord has Stripe
Tax enabled AND the charge is non-KES.

Admin UI: `/admin/gateways` → per-landlord tax-config POST to
`/admin/gateways/{user}/tax-config` (super_admin only). Validation
enforces the KRA PIN regex + range-checks vat_rate_bps_override
to [0, 10000].

## Phase 42 [PAYMENTS-INTL] PLAN-SYNC-AUTO

Opt-in auto-resolve for the `subscription_plan_drift` gauge
Phase 41 ships. Operator decides per-plan whether app or Stripe
wins price drift.

Schema:
- `subscription_plans.drift_resolve_mode` ENUM
  ('manual_review' default | 'always_app_wins' |
  'always_stripe_wins'). App\Enums\DriftResolveMode string-backed.
- `subscription_plan_drift_log` append-only audit trail — every
  detected drift gets a row regardless of resolution mode
  (subscription_plan_id, stripe_price_id, app_price_cents,
  stripe_price_cents, drift_resolve_mode_at_time, resolution
  enum, detected_at, resolved_at).

Service: `App\Services\Subscriptions\PlanDriftResolver`.
- `resolve(SubscriptionPlan, int $stripePriceCents, string $priceId)`
  writes the log row first, then branches on the plan's mode.
- ManualReview: flips resolution → manual_pending. Safe default.
- AlwaysAppWins: re-pushes local price to Stripe via
  StripeSubscriptionService::createOrUpdatePlan. Gracefully
  skips the Stripe call when system isn't configured (dev/test).
- AlwaysStripeWins: writes Stripe Price unit_amount back to
  plan.price_monthly via bcmath-safe number_format.
- Throwable caught + logged as warning so a single broken plan
  doesn't poison the webhook handler.

StripeWebhookController::handlePriceUpdated emits the legacy
`subscription_plan_drift` gauge (Phase-36 dashboards) THEN
delegates to PlanDriftResolver.

Admin UI: `/admin/gateways/plan-drift-mode/{plan}` (super_admin)
sets the mode. Index payload now carries `plans` + `driftLogRecent`
(last 30d, limit 50) + `driftResolveModes` for the Vue selector.

## Phase 42 [PAYMENTS-INTL] CONNECT-STANDARD

Stripe Connect Standard sibling to Phase-41 Express. Standard
accounts are owned by the landlord (their own Stripe dashboard
login, tax-filing independence) and use direct charges
(`Stripe-Account` header + `on_behalf_of`) instead of destination
charges. Pick is permanent — Stripe does not support converting
between account types after creation.

Schema:
- `payment_configurations.stripe_connect_account_type` ENUM
  ('express' default | 'standard'). App\Enums
  \StripeConnectAccountType string-backed.

Service: `App\Services\StripeConnectStandardService`.
Surface mirrors Phase-41 StripeConnectService exactly:
- createStandardAccount(User, country='US')
- onboardingLink(accountId, returnUrl, refreshUrl)
- syncAccountStatus(accountId)

StripeService::createPaymentIntent now routes per account type:
- account_id missing → legacy direct-to-platform charge.
- account_id + Express → `transfer_data.destination` (destination
  charge model; funds settle in platform account then transfer to
  the landlord after Stripe fees).
- account_id + Standard → `on_behalf_of` + Stripe-Account header
  (direct charge model; funds settle directly in landlord's
  account; landlord owns the merchant relationship).

When to recommend Standard: landlord has their own Stripe
account AND wants independent tax reporting (e.g., US/EU
landlords who file 1099-K / VAT). Express is the right default
for everyone else (PropManager-managed onboarding minimises the
landlord's lift).

Common Standard failure modes: capability not active → charges
fail with `reason=requirements_past_due`. Operator action:
`StripeConnectStandardService::syncAccountStatus(accountId)`
then check `stripe_connect_charges_enabled` + the Stripe
Dashboard "Verification" tab.

## Phase 42 [PAYMENTS-INTL] CART

Multi-line, multi-currency checkout. A single CheckoutSession
can carry KES rent + USD add-on; CartCheckoutService groups
items by currency and creates one PaymentIntent per group.

Schema:
- `checkout_sessions` (landlord_id, tenant_id NULL, status enum
  [open|submitted|succeeded|failed|expired], total_amount_cents,
  currency_breakdown JSON, expires_at, succeeded_at).
- `checkout_session_items` (checkout_session_id CASCADE, line_type
  ['invoice'|'add_on'|'deposit'], line_id polymorphic NULL,
  amount_cents, currency, description, sort_order,
  stripe_payment_intent_id).

Service: `App\Services\Checkout\CartCheckoutService`.
- `initialize(CheckoutSession)` returns
  `{CURRENCY: {client_secret, amount_cents, payment_intent_id,
  line_count}}` keyed by currency. Honours
  PaymentGatewayManager::routeForUser so user
  .payment_gateway_preference is respected per currency. Stamps
  the payment intent id back onto every line in the group.

Route: `POST /checkout/sessions/{session}/initialize` —
auth-gated, ownership-checked inside controller (tenant OR
landlord OR super_admin), 422 if session is not open.

Out of scope: FX / cross-currency conversion (one PaymentIntent
per currency stays the design; cross-currency netting is a Phase
44+ candidate).

## Phase 42 [PAYMENTS-INTL] METHODS

First-class users.id ↔ Stripe Customer.id mapping for saved-card
reuse and lower-friction returning checkouts.

Schema:
- `stripe_customers` (user_id FK UNIQUE, stripe_customer_id
  UNIQUE, default_payment_method_id NULL, soft_deletes for
  Phase-13 DPA-3 retention).

Service: `App\Services\StripeCustomerService` (system-wide
credentials — SaaS billing plane).
- `ensureCustomer(User)` idempotent: returns existing mapping
  or creates via $client->customers->create + DB::transaction
  persist. Returns null when Stripe isn't configured (dev/test
  degrade gracefully without orphan rows).
- `ensurePaymentMethodAttached(StripeCustomer, paymentMethodId,
  makeDefault=false)` attaches a PaymentMethod from a SetupIntent
  + optionally sets `invoice_settings.default_payment_method`.
- `detachPaymentMethod(paymentMethodId)`.

StripeWebhookController dispatch now covers three new event types:
- `customer.created` — upsert if metadata.user_id is set (operator
  may create Customers via Stripe Dashboard).
- `customer.updated` — sync `default_payment_method_id`.
- `customer.deleted` — soft-delete the mapping (retains audit
  trail; blocks future use).

## Phase 42 [PAYMENTS-INTL] PAYOUT-AUDIT

Detection for Stripe Connect payout failures (closed bank
accounts, identity verification expired, capability drift).

Cron: `payouts:stripe-balance-audit` runs twice daily at 03:15 +
15:15 Africa/Nairobi (`15 3,15 * * *`). For each PaymentConfig
with non-null stripe_connect_account_id, lists payouts in
landlord context (Stripe-Account header) with `status=failed +
created>=24h_ago`. Emits `stripe_payout_failure_count{landlord_id}`
gauge and opens sev3 OperationalIncident when count > 0.

Webhook: `payout.failed` handler gives real-time signal. Resolves
landlord via reverse lookup (payment_configurations
.stripe_connect_account_id == event.destination), emits
`stripe_payout_failure_count{source=webhook}` gauge, opens sev3
incident with failure_message preserved.

Alert: docs/runbooks/alert-thresholds.md "Stripe Connect payout
failures" row — threshold 5/24h. On-call action sequence:
Stripe Dashboard → Payouts → verify landlord bank account →
run StripeConnectService::syncAccountStatus → check
stripe_connect_status column for drift.

