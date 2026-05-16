# Tenant Portal (Phase 28)

Runbook for the tenant-facing surface introduced in Phase 28
[TENANT-PORTAL]. Mirrors the shape of `docs/runbooks/bi.md` and
`docs/runbooks/pwa.md`.

## Overview

Phase 28 closed the tenant-side self-service gap: dedicated tenant
profile + statement viewer + document repository + ticket SLA tracking
+ payment plan request + deposit refund request, plus a per-tenant
abilities map on the Inertia share and a route-coverage watchdog.

Tier 4 cycle 1 (after Tier 3 platform + API excellence complete).

## Route tree

All routes live under `/tenant/` prefix and are gated by the chain:
`auth` → `role:tenant` → `payment.verified` → `kyc.complete`. The
KYC + payment middleware redirect the tenant out of the portal when
the precondition is not met (to `/tenant/payment-required` or
`/tenant/complete-profile` respectively).

| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | `/tenant/profile` | `TenantProfileController@edit` | `tenant.profile.edit` |
| PATCH | `/tenant/profile` | `TenantProfileController@update` | `tenant.profile.update` |
| PATCH | `/tenant/profile/password` | `TenantProfileController@updatePassword` | `tenant.profile.password` |
| PATCH | `/tenant/profile/notification-prefs` | `TenantProfileController@updateNotificationPrefs` | `tenant.profile.notification-prefs` |
| GET | `/tenant/statement` | `TenantStatementController@index` | `tenant.statement.index` |
| GET | `/tenant/statement.pdf` | `TenantStatementController@pdf` | `tenant.statement.pdf` |
| GET | `/tenant/statement.xlsx` | `TenantStatementController@xlsx` | `tenant.statement.xlsx` |
| POST | `/tenant/statement/email` | `TenantStatementController@email` | `tenant.statement.email` |
| GET | `/tenant/documents` | `TenantDocumentsController@index` | `tenant.documents.index` |
| GET | `/tenant/documents/{document}/download` | `TenantDocumentsController@download` | `tenant.documents.download` |
| POST | `/tenant/payment-plans/request` | `Tenant\PaymentPlanRequestController@store` | `tenant.payment-plans.request` |
| POST | `/tenant/deposit-refunds` | `Tenant\DepositRefundController@store` | `tenant.deposit-refunds.store` |

The pre-Phase-28 tenant surface (lease view, finances index/pay/history,
notifications, payment verification, KYC complete) is unchanged.

`Phase28TenantSurfaceTest` enforces that every route under `/tenant/`
carries `role:tenant` middleware (whitelist: `tenant.payment-required`,
`tenant.payment.submit`, `tenant.payment.pay-online` — pre-KYC routes).

## Ability matrix (Inertia share)

`HandleInertiaRequests::share()` exposes `auth.tenant_abilities` as
`array<string, bool>` for tenant requests (null otherwise). Keys are a
contract — adding a new key requires updating `TenantAbilities::ABILITY_KEYS`
**and** this runbook table; `Phase28CiTest` enforces parity.

| Key | True when |
|-----|-----------|
| `statement:download` | always (every tenant can download own statement) |
| `statement:email` | always (recipient hard-wired to authenticated user) |
| `documents:view_kyc` | `$user->hasCompletedKyc()` |
| `tickets:create` | always |
| `payment_plan:request` | active lease has an unpaid invoice AND no active plan |
| `deposit:request_refund` | move-out is `completed` AND no active refund request |

Tenant Vue pages consume via `usePage().props.auth.tenant_abilities[key]`.

## Schema additions

| Table | Columns added/created | Phase finding |
|-------|----------------------|---------------|
| `documents` | `expires_at` DATE NULL + index | TENANT-DOCS-3 |
| `tickets` | `sla_due_at` TIMESTAMP NULL, `first_response_at` TIMESTAMP NULL, composite `tickets_sla_idx` | TENANT-MAINT-1 |
| `payment_plans` | new — `landlord_id`, `tenant_id`, `invoice_id`, `total_amount_cents`, `status`, timestamps | TENANT-PAY-1 |
| `payment_plan_installments` | new — `payment_plan_id`, `due_date`, `amount_cents`, `paid_amount_cents`, `status` | TENANT-PAY-1 |
| `deposit_refund_requests` | new — `landlord_id`, `tenant_id`, `lease_id`, `requested_amount_cents`, `payment_method`, `payment_details` JSON, `status`, audit timestamps | TENANT-PAY-3 |

Existing `notification_preferences` (12 types × 5 channels matrix) is
reused for TENANT-PROFILE-2 — no schema change.

## CI gates

Watchdog test classes under `tests/Feature/TenantPortal/`:

- `Phase28ProfileTest` — dedicated tenant profile + notification matrix
  surface + locale picker + landlord redirect
- `Phase28StatementTest` — StatementService running balance + PDF/xlsx
  export + email-me Phase-13 PERSONAL-DATA-1 compliance
- `Phase28DocsTest` — Documents page grouping + DocumentPolicy
  cross-tenant block + expiry banner shared prop + tenant-only gating
- `Phase28MaintTest` — Ticket SLA columns + booted hook +
  first_response_at semantics + audit command idempotency + Phase-16
  backoff + multi-photo validation + schedule registration
- `Phase28PayTest` — payment plan request flow + installment splitting
  with cent remainder + duplicate-plan block + deposit refund flow +
  M-Pesa phone format validation
- `Phase28TenantSurfaceTest` — route-coverage watchdog: every
  `/tenant/*` route carries `role:tenant`
- `Phase28CiTest` — TenantAbilities key parity with this runbook +
  tenant_abilities shared shape

## Ops procedures

### `tickets:audit-sla`

Schedule: `dailyAt('07:00')` Africa/Nairobi, `onOneServer()`.

Counts breached tickets per priority and emits Prometheus gauge
`ticket_sla_breach_count{priority=urgent|high|medium|low}` for each
bucket (including 0 for clean buckets). Fires `TicketSlaBreached` per
row with Cache idempotency (24h lock keyed on
`ticket_id + sla_due_at`). The listener
`NotifyOnTicketSlaBreach` notifies the landlord + every caretaker
under the landlord via `NotificationService::send` (consults the
NotificationPreference matrix for delivery channels).

`--dry-run` flag emits the gauge but does not fire events — useful
for surfacing the breach count to dashboards without re-alerting.

## Deferrals

- **TENANT-PAY-2** (auto-allocate payment to plan installments + nightly
  `payment-plan-allocations:audit`) — deferred from Phase 2. The
  PaymentAllocationService extension touches the existing money
  reconciliation path and warrants a dedicated commit with its own
  golden test fixture. Lands in Phase 21-style consolidation or
  Phase 30.
- Landlord-side approval UIs for payment plans + deposit refunds —
  Phase 2 ships the tenant-side flow + the data model + the policies.
  Landlord approve/reject/mark-paid lands in a follow-up so this audit
  cycle stays scoped to the tenant portal.

## Memory + lineage

See `project_propmanager_phase28_plan.md` and
`project_propmanager_path_forward.md` Phase 28 marker.
