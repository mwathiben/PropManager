# Policy + Index Conventions (Phase-19)

This runbook documents the authorization-layer + database-index conventions established in Phase-19 (POLICY + INDEX audit). Use it when:
- Adding a new Policy class
- Issuing a new Sanctum ability
- Adding a foreign key / composite index to a TenantScope-using table
- Adding or revising a cross-tenant artisan command

The Phase-18 `docs/runbooks/authz-and-data.md` covers the cross-tenant write-path observers + DPA-4 Gate::before order + FK onDelete conventions; this runbook is the **completeness layer** underneath that.

---

## 1. Policy method conventions

### 1.1 Soft-deleted models — declare `forceDelete` + `restore`

If the model uses `SoftDeletes`, the registered Policy MUST declare both:

```php
public function forceDelete(User $user, MyModel $m): bool
{
    // Super-admin only via Policy::before. Non-super-admin
    // cannot force-delete — this is a destructive op.
    return false;
}

public function restore(User $user, MyModel $m): bool
{
    // Mirror delete() ownership: the user who could delete
    // can undo the soft-delete.
    return $user->isLandlord() && $m->landlord_id === $user->id;
}
```

**Phase-19 POLICY-1** closed this gap for 8 SoftDelete-using policies (Building, Document, Invoice, KycRequirement, Lease, Property, Unit, TenantPolicy).

### 1.2 Standard CRUD method completeness

Every registered Policy should declare the 5 standard methods (`viewAny`, `view`, `create`, `update`, `delete`) AND `forceDelete` + `restore` if the model soft-deletes — even if the answer is an explicit `return false`. The Phase-19 POLICY-2/3/4 fixes shipped explicit deny methods on TenantPaymentVerificationPolicy + TenantPolicy + ImportPolicy where the read-only-by-design invariant was previously implicit.

**Why explicit deny over missing method**: Laravel's default for a missing Policy method is deny (post-5.7), but the contract is invisible at the Policy layer. A future controller adding `$this->authorize('update', $model)` silently passes through framework default. Declaring the method makes the intent visible to code review + the AuthzCoverageMatrix watchdog.

### 1.3 Inline `isSuperAdmin()` checks in controllers

**Forbidden pattern**: `if (! $user->isSuperAdmin()) abort(403)` directly in controller actions.

**Reason**: Inline checks BYPASS the Gate layer, which means the Phase-13 DPA-4 restriction `Gate::before` hook never fires. A DPA-restricted super-admin would still be able to act through that controller.

**Required pattern**: `Gate::authorize('access-admin')` (or a domain-specific gate). Phase-18 AUTHZ-3 fixed AdminController; Phase-19 POLICY-5 fixed SubscriptionController; Phase-19 POLICY-6 added the `User::canAccessAllAuditLogs()` helper for AuditLogController's scope path. The pattern: combine the role check WITH the restriction check into a single User method that's then used in the controller's query scoping.

### 1.4 Raw role-string comparisons are forbidden

**Forbidden**: `$user->role === 'landlord'`, `$user->role === 'super_admin'`.

**Required**: `$user->isLandlord()`, `$user->isSuperAdmin()`, `$user->isCaretaker()`, `$user->isTenant()`.

`Phase18Phase3Test::test_no_raw_role_string_comparison_in_controllers` is the watchdog (Phase-19 tightened baseline 8 → 6, threshold 10 → 8).

---

## 2. Sanctum ability + Gate parity

### 2.1 Every Sanctum ability needs a Gate mirror

When `AuthController::getAbilitiesForUser` issues a new ability like `'integration:webhook'`, AuthServiceProvider MUST `Gate::define` a matching gate:

```php
Gate::define('integration:webhook', function ($user) {
    // Super-admin handled by Gate::before bypass. This hook
    // only fires for non-super-admin token holders — they must
    // hold a Sanctum token with the ability.
    return $user->tokenCan('integration:webhook');
});
```

**Reason**: DPA-4 restriction enforcement. `Gate::before` fires the Phase-13 DPA-4 hook for every Gate::allows / authorize call. Without the Gate mirror, the only check is `tokenCan(...)` which is purely Sanctum and bypasses the restriction layer.

**Phase-19 POLICY-7** closed this for `integration:webhook`. ReportController's `resolveLandlordId` now uses `Gate::allows('integration:webhook')` instead of inline `tokenCan()`.

### 2.2 Ability naming convention

- Sanctum abilities: `domain:action` lowercase (e.g. `landlord:manage`, `integration:webhook`, `tenant:read`).
- Gate abilities: kebab-case (e.g. `access-admin`, `view-audit-logs`, `manage-subscription`). 
- Sanctum abilities mirrored as Gates: keep the Sanctum format (e.g. `integration:webhook` is the Gate name too) — exact string match makes the parity grep-discoverable.

---

## 3. Index conventions

### 3.1 TenantScope composite indexes

Every TenantScope-using table SHOULD have a `(landlord_id, primary_date)` composite index, where `primary_date` is the dominant filter column for that domain:

| Table | Composite | Phase added |
|---|---|---|
| `payments` | `(landlord_id, payment_date)` | 15 PERF-1 |
| `invoices` | `(landlord_id, status, due_date, total_due, amount_paid)` | 19 INDEX-5 (covering) |
| `notifications` | `(recipient_id, read_at)` | 15 PERF-7 |
| `buildings` | `(landlord_id, id)` | 19 INDEX-4 |
| `units` | `(landlord_id, id)` | 19 INDEX-4 |
| `properties` | `(landlord_id, created_at)` | 19 INDEX-4 |
| `wallet_transactions` | `(landlord_id, created_at)` | 19 INDEX-4/8 |

### 3.2 FK leading-index rule

`->foreignId('x')->constrained()` does NOT auto-add an index on column `x`. InnoDB requires a leading FK index for reverse-direction joins + correct lock scope on inserts. Add `$table->index('x')` immediately after the FK in the same migration.

**Phase-19 INDEX-3 + INDEX-7** closed this for `invoice_items.invoice_id` + 5 expense FK columns.

### 3.3 Covering indexes

MySQL 8.0 has no PostgreSQL-style `INCLUDE` syntax — to get a covering plan, list all referenced columns in the index. The trade-off: larger index pages, eliminates heap fetches.

**Phase-19 INDEX-5** upgraded `invoices (landlord_id, status, due_date)` to `(landlord_id, status, due_date, total_due, amount_paid)` for the arrears + revenue report hot path. The 3-column prefix was dropped in the same migration to avoid redundancy.

When considering a covering index: only do it if the query is on the hot-path slow-query log and the SELECT list is small + stable.

### 3.4 Denormalization drift audits

Any time you cache an aggregate on a parent row (e.g. `invoice.amount_paid`, `invoice.late_fees_total`, `lease.wallet_balance`), ship a nightly audit command alongside the migration:

```php
// Pattern: app/Console/Commands/Audit<Aggregate>Allocation.php
DB::table('parents')
    ->leftJoin('children', ...)
    ->select('parents.id', 'parents.cached', DB::raw('SUM(children.value) as actual'))
    ->groupBy(...)
    ->havingRaw('ABS(parents.cached - SUM(children.value)) > 0.01')
    ->get();
// Emit Prometheus gauge: <table>_<column>_drift_count
// Exit FAILURE on drift > threshold so the schedule channel alerts.
```

Existing audits:
- `payments:audit-allocations` — Phase-17 MONEY-5 (invoice.amount_paid)
- `wallets:audit-balances` — Phase-18 DATA-2 (lease.wallet_balance)
- `latefees:audit-drift` — Phase-19 INDEX-1 (invoice.late_fees_total)

Schedule them 5-10 minutes apart in `routes/console.php` to avoid Prometheus gauge race conditions.

---

## 4. Cross-tenant artisan command guard rails (Phase-19 POLICY-9)

Commands that mutate data across multiple landlords MUST accept the following flags:

| Flag | Required when | Behavior |
|---|---|---|
| `--landlord-id=N` | Operator override | Scope mutation to one landlord |
| `--dry-run` | Pre-flight check | Report what WOULD change; no DB writes |
| `--confirm` | Interactive use | Skip refusal-prompt; required if `isInteractive()` returns true |
| `--max-deletions=N` | Destructive ops only | Safety cap; abort if scope exceeds (default 50 for deletion) |

Scheduler invocations (`routes/console.php` `$schedule()`) run non-interactively, so the `--confirm` requirement is automatically skipped. Operator shell invocations require `--confirm` explicitly.

Pattern in command `handle()`:

```php
if (! $dryRun && ! $this->option('confirm') && $this->input->isInteractive()) {
    $this->error('Refusing to run without --confirm in interactive mode (POLICY-9).');
    return self::FAILURE;
}
```

Updated commands:
- `invoices:generate` (Phase-19 POLICY-9)
- `invoices:mark-overdue` (Phase-19 POLICY-9)
- `gdpr:process-deletions` (Phase-19 POLICY-9 + `--max-deletions` cap)

---

## 5. Deferred to Phase 20+

These Phase-19 PRD items were not shipped — left here so the next audit cycle picks them up:

- **POLICY-8** — Frontend `@can` / `v-can` directives across Blade + Vue templates. Needs (a) Inertia `HandleInertiaRequests` middleware change to share `auth.user.abilities`, (b) Vue 3 v-can directive registration, (c) seeding 10-15 templates. Substantial UX-coordination work; deferred to a frontend-focused phase.
- **INDEX-6** — Cursor pagination on `audit_logs` + `tenant_activities`. The change requires a `CursorPagination.vue` component (cursor paginator has no `from`/`to`/`total` counters), Vue page rewrites, and the Phase-7-flagged `api/v1/audit-logs` v2 endpoint migration. Capacity-planning work, not yet load-driven.
- **INDEX-9** — SoftDelete partial-index workaround via `STORED GENERATED` columns on buildings/units/documents. The `STORED` column addition is NOT an online InnoDB operation — it rewrites the table. Requires operator-coordinated maintenance window per table.

---

## 6. Test infrastructure used by Phase-19

- `tests/Feature/Security/Phase19PolicyTest.php` — POLICY-1/5/6 high-severity coverage (forceDelete/restore × 8 policies, Gate-routed manage-subscription, audit-log scope for restricted super-admin).
- `tests/Feature/Security/Phase19IndexTest.php` — INDEX-1 latefees:audit-drift functional + schedule wiring.
- `tests/Feature/Security/Phase19Phase3Test.php` — POLICY-2/3/4 method completeness + 13 schema-introspection assertions for the new indexes.
- `tests/Feature/Security/Phase19Phase4Test.php` — POLICY-7 integration:webhook Gate parity + POLICY-9 command guard rails.

The Phase-18 watchdogs (`AuthzCoverageMatrixTest`, `Phase18Phase3Test`) still apply — Phase-19 only tightened the `no_raw_role_string_comparison_in_controllers` threshold from 10 → 8 and updated the dead-Gate denylist to allow `manage-subscription`.
