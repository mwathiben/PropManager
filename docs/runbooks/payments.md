# Payments runbook

Operator reference for the Phase-40 [STRIPE-GATEWAY] payment-rail
surface: PaymentGatewayInterface contract, Paystack + M-Pesa + Stripe
implementations, reconciliation, currency routing.

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
live in `settings` table via `Setting::getSystem('paystack_secret_key')`
/ `Setting::getSystem('stripe_secret_key')`.

| Service | Loads credentials via |
|---------|----------------------|
| `PaystackSubscriptionService` | `Setting::getSystem(...)` |
| `StripeSubscriptionService` | `Setting::getSystem(...)` (lands Phase 1b) |

When landlord pays their monthly PropManager subscription → flows to
PropManager's own gateway account → settles to PropManager's bank.

### Rule of thumb

- Money flowing **to** a landlord → per-landlord credentials.
- Money flowing **to** PropManager → system-wide credentials.
- Mixing them is a Phase-40 [GATEWAY-CONTRACT-3] violation.

## Currency routing (lands Phase 1d)

Default routing (Phase 40 [GATEWAY-CURRENCY-3]):

| Currency | Gateway |
|----------|---------|
| KES | Paystack (domestic) |
| USD, EUR, GBP | Stripe |

Override per-landlord via `users.payment_gateway_preference`
enum (paystack | stripe | auto). `auto` means: follow the table above.

## Gateway slot inventory

Phase 40 [GATEWAY-CONTRACT-1] registered `stripe` in
`PaymentGatewayManager`. Current slots:

```
PaymentGatewayManager::supportedGateways() = ['paystack', 'mpesa', 'stripe']
PaymentGatewayManager::gateway($name): PaymentGatewayInterface
PaymentGatewayManager::paystack(): PaystackGateway
PaymentGatewayManager::mpesa(): MpesaGateway
PaymentGatewayManager::stripe(): StripeGateway
PaymentGatewayManager::available(): array<string, PaymentGatewayInterface> (filters by isConfigured())
PaymentGatewayManager::routeFor(Currency $currency): PaymentGatewayInterface  (Phase 1d)
```

## Subsequent sections

Reconciliation, webhook configuration, drift alerts, super_admin
gateway switcher UI — land in Phase 1b/1c/1d/1e/1f. See
`phase-40-audit-prd.json` for the full surface map. Update this
runbook at each phase closeout.
