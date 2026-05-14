# Frontend Authz + UX Conventions (Phase-20)

This runbook documents the frontend-authorization + user-perceived UX conventions established in Phase 20 (FRONT + UX-AUTHZ audit). Use it when:

- Adding a new button that should be permission-gated
- Sharing per-record abilities from a resource controller
- Writing a form that calls Inertia useForm()
- Building a list view that may have an empty state
- Designing a modal/dialog
- Choosing between offset and cursor pagination

The Phase-19 `docs/runbooks/policy-and-index.md` covers the server-side policy + index conventions; Phase-20 is the UI surface complement.

---

## 1. Inertia abilities-share contract

The `auth.user.abilities` map is shared by `app/Http/Middleware/HandleInertiaRequests.php` via `App\Support\UserDto::from($user)` which calls `App\Support\AuthAbilities::for($user)`.

Stable key set (Phase-20):

| Ability | Granted to | Notes |
|---|---|---|
| `access-admin` | super-admin only (read-side allow-list) | Admin panel nav gating |
| `view-audit-logs` | super-admin + landlord (read-side allow-list) | Audit log index visibility |
| `view-security-logs` | super-admin only (read-side allow-list) | Security log nav |
| `manage-subscription` | landlord only | Phase-19 POLICY-5 |
| `export-data` | all users (read-side allow-list) | GDPR export |
| `request-deletion` | all users (read-side allow-list) | GDPR deletion request |
| `integration:webhook` | super-admin + integration token holders | Phase-19 POLICY-7 |

**Adding a new ability**: extend `AuthAbilities::for()` AND the TypeScript `User` interface in `resources/js/composables/useAuth.ts`. The `Phase20AuthzFrontTest::test_auth_abilities_returns_flat_boolean_map_for_super_admin` test pins the key set — drift breaks the test.

DPA-4 enforcement is automatic: `Gate::before` denies any ability NOT on the read-side allow-list when `restricted_at` is set. A restricted user's `manage-subscription` will be false even for the landlord role.

---

## 2. v-if vs v-can — gating buttons in templates

PropManager uses Inertia + Vue 3. Vue 3 directives don't reactively re-evaluate on page navigation alone (the directive's `binding.value` is static), so the project uses the `useAuth().can()` composable in `v-if` expressions instead of a custom v-can directive.

**Canonical pattern**:

```vue
<script setup lang="ts">
import { useAuth } from '@/composables/useAuth';
const { can, isRestricted } = useAuth();
</script>
<template>
    <button v-if="can('access-admin')" @click="...">Admin Action</button>
    <button v-if="can('view-audit-logs')" @click="...">View Logs</button>
</template>
```

**Forbidden patterns**:

- `v-if="$page.props.auth.user.role === 'super_admin'"` — raw role-string comparison; same anti-pattern the Phase-18 server-side watchdog catches.
- `v-if="isSuperAdmin"` for action-button gating — confuses "what role this user has" with "what action they can take". Use `can('...')` so DPA-4 restriction propagates.

`isSuperAdmin`/`isLandlord` ARE still appropriate for layout-level routing (which sidebar to render), where the question is "what role's UI should this user see", not "is this user authorized to act".

---

## 3. Per-record abilities (Phase-20 AUTHZ-FRONT-5 — deferred to Phase 21)

Some abilities depend on the specific model instance (e.g. can the current user update *this* invoice?). For these, the global abilities map is insufficient. The Phase-21 pattern:

```php
// In InvoiceController::show()
return Inertia::render('Invoices/Show', [
    'invoice' => [
        // ...invoice fields...
        'abilities' => [
            'update' => $request->user()->can('update', $invoice),
            'delete' => $request->user()->can('delete', $invoice),
            'void' => $request->user()->can('void', $invoice),
        ],
    ],
]);
```

```vue
<template>
    <button v-if="invoice.abilities.void" @click="void">Void</button>
</template>
```

Per-record abilities are NOT in the global `auth.user.abilities` map (which is per-request, not per-resource). Phase 20 ships the framework; Phase 21+ migrates resource controllers as the per-record gating becomes needed.

---

## 4. DPA-4 restricted-user UX

When `auth.user.is_restricted` is true (Phase-13 Article 18 / Kenya DPA Section 26(d)):

1. **Banner in `AuthenticatedLayout.vue`** — amber banner with role="alert" + link to `gdpr.index`. Always visible.
2. **Action buttons** — the abilities map already returns false for write-side abilities while restricted, so `v-if="can('manage-subscription')"` etc. hide the buttons.
3. **Impersonation** — when a super-admin impersonates a restricted user, the impersonation banner appends "— read-only (Article 18)" suffix in red so the operator sees the state.

Form-level read-only mode (disabling individual inputs based on restriction state) is a Phase-21 candidate; for now the banner + button-hiding is sufficient.

---

## 5. Form submission pattern

Use Inertia's `useForm()` for client-side form state. Bind submit buttons via the `processing` flag to prevent double-submission:

**Canonical pattern (new forms)**:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormSubmitButton from '@/Components/FormSubmitButton.vue';

const form = useForm({ amount: '', description: '' });
const submit = () => form.post(route('payments.store'));
</script>
<template>
    <form @submit.prevent="submit">
        <!-- inputs -->
        <FormSubmitButton :processing="form.processing" variant="primary">
            Record Payment
        </FormSubmitButton>
    </form>
</template>
```

**Variants**: `primary` (indigo, default), `danger` (red, for destructive actions), `secondary` (white/border).

**Existing forms using bespoke `:disabled="form.processing"`** patterns are fine; the watchdog applies to new forms. Money-handling forms should migrate to FormSubmitButton when touched for any reason.

**Filter forms (GET-style)** don't need processing state — they're idempotent reads.

---

## 6. Empty states

Use `resources/js/Components/EmptyState.vue` for list views that may return zero rows:

```vue
<template>
    <EmptyState
        :icon="DocumentTextIcon"
        title="No invoices found"
        description="Adjust your filters or generate a new invoice."
        action-label="Create invoice"
        :action-href="route('invoices.create')"
    />
</template>
```

Phase-20 migrated `Invoices/Index.vue` + `Admin/AuditLogs.vue` empty states. The `Tenant/Notifications.vue` empty state was the original inspiration and is already canonical.

---

## 7. Modal accessibility

`resources/js/Components/Modal.vue` uses three a11y composables:

- `useEscapeKey` — close on Esc
- `useBodyScrollLock` — prevent page scroll behind the modal
- `useFocusTrap` (Phase-20 FRONT-UX-7) — trap Tab + Shift+Tab inside the modal

Adding a new modal? Use the existing Modal component. Building a custom dialog? Wire all three composables.

---

## 8. Pagination strategy — when to use what

| Pattern | Component | Use when |
|---|---|---|
| Offset (1/2/3/Next) | `Pagination.vue` | Bounded tables: Tenants, Invoices, Buildings, Leases. < 100k rows. |
| Cursor (Prev/Next only) | `CursorPagination.vue` | Unbounded log tables: audit_logs, tenant_activities. Phase-20 FRONT-UX-1 migrated these. |

Cursor paginator response shape (no from/to/total counters):

```
{ data: [...], per_page: 25, next_page_url: string|null, prev_page_url: string|null }
```

Adding a new unbounded log table? Use cursor pagination + the `(landlord_id, created_at, id)` composite index. See `docs/runbooks/policy-and-index.md` section 3.

---

## 9. Status badges (FRONT-UX-8)

`resources/js/Components/Badge.vue` color shades are pinned to `-900` text on `-100` background for WCAG-AA contrast at 14px. Don't introduce custom `bg-{color}-100 text-{color}-800` pairs — yellow-800 measured ~4.1:1, below the 4.5:1 AA minimum.

For state badges, prefer icon + text over color alone (color-blind affordance). The Badge component accepts an `<icon>` slot for this.

---

## 10. Sensitive-field policy in the share payload

`App\Support\UserDto::from()` is the contract. Forbidden fields in the slim DTO:

- `password`, `remember_token`
- `two_factor_secret`, `two_factor_recovery_codes`
- `restricted_at` (use computed `is_restricted` bool instead)
- `paystack_customer_code`
- `phone` (PII, unless needed for the UI flow)
- `created_at`, `updated_at`, `email_verified_at`

If a new field is needed, add it to `UserDto::from()` AND the TypeScript `User` interface — `Phase20AuthzFrontTest::test_user_dto_does_not_leak_sensitive_eloquent_fields` is the watchdog.

---

## 11. Deferred to Phase 21+

These Phase-20 PRD items are documented but not yet shipped:

- **AUTHZ-FRONT-2 broader template seeding** — `useAuth().can()` framework is shipped + 1 canonical example (AuthenticatedLayout admin nav). Seeding the remaining ~10-15 high-impact templates (Tenants/Show delete-note, Invoices/Show void, etc.) is incremental adoption — defer as those pages are touched.
- **AUTHZ-FRONT-5 per-record abilities** — pattern documented in section 3; per-controller adoption is Phase-21+.
- **AUTHZ-FRONT-7 client-side route guards + Errors/403.vue+404.vue+500.vue pages** — server still 403s; UX-affordance gap. Phase-21+ candidate.
- **FRONT-UX-2 broader FormSubmitButton adoption** — component shipped + convention documented. Existing forms with bespoke `:disabled` work fine; migrate on touch.
- **FRONT-UX-4 optimistic notification mark-as-read on Tenant/Notifications.vue** — NotificationBell already optimistic; PageNotifications still does router.reload(). Phase-21+.
- **FRONT-UX-6 IconButton component with required aria-label prop** — pattern documented; component creation + migration is Phase-21+ ergonomics work.
- **Phase-19 INDEX-9 SoftDelete STORED-column workaround** — still deferred; needs operator maintenance window.
