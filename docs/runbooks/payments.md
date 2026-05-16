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

## Phase 41 [GATEWAY-DEEP] handoff notes

Stripe gateway is functionally complete but several deeper-end items
defer to the next cycle:

- **Remote charge fetch for Stripe reconcile**: `reconcileStripe` is
  local-only today. When the first landlord enables Stripe rent
  collection in production, wire `Stripe\Charge::list` for the
  remote diff loop (mirrors `PaystackService::listTransactions`).
- **PaymentReconciliationService::reconcile generic compare loop**:
  per-gateway impl methods still hand-roll the indexing. Once Stripe
  has a real remote fetcher, hoist the compare/discrepancy loop into
  a gateway-agnostic helper.
- **Stripe Connect per-tenant subaccounts**: Phase 30 shipped
  `PaystackSubaccountService` for split-payments. Stripe Connect
  Express equivalent deferred until at least one landlord asks for
  USD subaccount split.
- **`payment_intent.succeeded` + `charge.refunded` handlers**:
  StripeWebhookController only handles `customer.subscription.*` today.
  Add these handlers in the same `match` once the first landlord
  takes a USD/EUR/GBP payment via Stripe in production.
- **Tenant-facing currency picker**: defaults to landlord's
  preferred currency from `PaymentConfiguration`. UI for tenant to
  select currency at checkout deferred until international tenants
  land.
- **Bidirectional SubscriptionPlan ↔ Stripe Price sync**:
  `StripeSubscriptionService::createOrUpdatePlan` is one-way only
  (PropManager pushes to Stripe). Stripe-side price edits don't
  back-sync. Defer until product team needs Stripe-driven plan
  experimentation.
