# Authz + Data Conventions (Phase-18)

This runbook captures the AUTHZ + DATA conventions established by the Phase-18 audit. Read it before introducing new controllers, Policies, Gates, or migrations.

## Authorization

### Pick the right gate type

| Use case | Gate type | Example |
|----------|-----------|---------|
| Action gated by role (landlord vs caretaker vs tenant) | Route middleware | `Route::middleware('role:landlord')` |
| Action gated by Eloquent ownership (this landlord's invoice) | Policy method + `$this->authorize` | `$this->authorize('update', $invoice)` |
| Cross-cutting concern (admin panel, audit logs, export-data) | `Gate::define` + `Gate::authorize` | `Gate::authorize('access-admin')` |
| API endpoint with token scopes | Sanctum ability | `Route::middleware('abilities:landlord:manage')` |

**Never** mix patterns within a single controller — pick one and document it in the constructor docblock.

### Existing Gates (Phase-18 inventory)

| Gate | Defined in | Enforced by |
|------|-----------|-------------|
| `access-admin` | AuthServiceProvider | AdminController constructor middleware (Phase-18 AUTHZ-3) |
| `impersonate` | AuthServiceProvider | AdminController::impersonate inline `Gate::allows` |
| `view-security-logs` | AuthServiceProvider | AuditLogController + admin paths |
| `view-audit-logs` | AuthServiceProvider | AuditLogController |
| `export-data` | AuthServiceProvider | GdprController paths (Phase-13 DPA-4 allow-list) |
| `request-deletion` | AuthServiceProvider | GdprController paths (Phase-13 DPA-4 allow-list) |

**Deleted in Phase-18 AUTHZ-1**: `manage-caretakers`, `generate-invoices`, `perform-bulk-operations`, `access-reports`, `manage-subscription`. All five had zero call sites; the route-middleware `role:landlord` etc. enforces the equivalent constraint.

### `Gate::before` ordering (Phase-18 AUTHZ-8)

```
1. DPA-4 restriction check (deny write-side abilities while restricted_at set)
2. Super-admin bypass (return true for any non-DPA-4-blocked ability)
3. Policy class
```

A super-admin who has been DPA-restricted is actually restricted. The DPA-4 hook runs first and short-circuits.

### `$user->role === 'landlord'` is forbidden

Use `$user->isLandlord()` / `$user->isCaretaker()` / `$user->isSuperAdmin()` / `$user->isTenant()` consistently. A raw string comparison hides a typo (`landloard`) and silently grants/denies. The `AuthzCoverageMatrixTest::test_no_raw_role_string_comparisons` (Phase-18 AUTHZ-6) scans the controller layer for this pattern.

### Sanctum abilities

API tokens declare per-token abilities. Today's tokens use the slugs `landlord:manage`, `tenant:manage`, `tenant:read`, `tenant:write`. Route declarations gate via `Route::middleware('abilities:landlord:manage')`. There's no central registry yet — when adding a new ability, add an entry to this runbook AND a test in AuthzCoverageMatrixTest.

## Data integrity

### FK convention (Phase-18 DATA-9)

| Table type | onDelete | Rationale |
|-----------|----------|-----------|
| Append-only log tables (audit_logs, security_logs, webhook_logs) | `nullOnDelete()` | Audit trail must survive user deletion |
| Financial records (payments, refunds, late_fees, deposit_transactions) | `restrictOnDelete()` | Phase-13 DPA-3 7-year retention; cascade would silently violate it (Phase-18 DATA-1) |
| Structural ownership (properties → buildings → units → leases) | `cascadeOnDelete()` | Hard-delete propagates structurally; SoftDelete is the normal retire path |
| Optional references (payment.tenant_id where tenant may leave) | `nullOnDelete()` | Preserve the payment record even after the tenant leaves |

**Phase-18 DATA-1** flipped `payments.invoice_id` from CASCADE to RESTRICT to align with the financial-records row in the table above.

### Cross-tenant invariant (Phase-18 DATA-5)

Every multi-tenant model must agree on `landlord_id`. The TenantScope trait filters READS to a single landlord. The WRITE path is guarded by observers — see `PaymentObserver::assertCrossTenantConsistency` for the canonical pattern. A new model with FKs to other landlord-scoped models needs the equivalent observer.

### Soft-delete cascade (Phase-18 DATA-6)

Eloquent's SoftDelete writes `deleted_at` WITHOUT triggering DB cascade. A soft-deleted Property leaves its Buildings/Units/Leases live unless the deletion path explicitly retires them. Phase-18 PropertyObserver + UnitObserver refuse to delete a parent while live descendants exist. The operator path is: retire descendants first (LeaseController::terminate → unit goes idle → unit can soft-delete → building can soft-delete → property can soft-delete).

### Denormalization drift audits

| Denormalized field | Source-of-truth aggregate | Audit command |
|--------------------|----------------------------|---------------|
| `invoice.amount_paid` | `SUM(payments.amount WHERE invoice_id=N)` | `payments:audit-allocations` (Phase-17 MONEY-5) |
| `lease.wallet_balance` | `SUM(wallet_transactions credits) - SUM(debits)` | `wallets:audit-balances` (Phase-18 DATA-2) |
| `invoice.late_fees_total` | `SUM(active late_fees.fee_amount)` | `payments:audit-allocations --include-late-fees` (Phase-18 DATA-4 extension) |

All three exit FAILURE (1) on drift > 0.01 KES, emit a Prometheus gauge `<field>_drift_count`, and log to the schedule channel.

### Orphan row audit

`php artisan data:audit-orphans` (scheduled weekly Sunday 06:00 Africa/Nairobi) checks the canonical FK relationships for orphan rows: leases pointing at soft-deleted Units, invoices pointing at soft-deleted Leases, audit_logs/security_logs with stale `user_id`. Counts emit `data_orphan_row_count{kind=X}` gauges.

### Tenant-scoped table index convention (Phase-18 DATA-8)

Every tenant-scoped table (TenantScope trait) must have a composite index `(landlord_id, <primary_date_field>)`. `<primary_date_field>` is typically `created_at` for log tables, `payment_date` for payments, `due_date` for invoices, etc. The Phase-15 PERF-1/2 indexes (`payments_landlord_date_idx`, `invoices_landlord_status_due_idx`) are examples.

A migration that ships a new tenant-scoped table without the composite index will be caught by the Phase-18 tenant-scope index audit (not enforced as a CI gate today — manual review).

## See also

- `docs/runbooks/money-and-time.md` — Phase-17 conventions
- `docs/runbooks/circuit-breaker.md` — Phase-16 RESIL-1
- `docs/runbooks/queue-triage.md` — Phase-16 QUEUE-5
