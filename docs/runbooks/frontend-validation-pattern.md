# Frontend Form Validation — Convention

Phase-15 FRONT-7: PropManager's Vue forms today rely 100% on server-side validation. The server is the only line of defence — which is the correct security posture — but the UX is poor (every validation failure is a 422 redirect with flash messages, no inline field-level errors). This document pins the pattern for adding inline validation when needed, without weakening the server as the source of truth.

## Pinned approach

**Zod schemas + Inertia's `useForm` — bridged by `useZodForm`.**

> **Phase-21 DEFER-FRONT-2 update.** Phase-15 originally pinned **vee-validate
> 4.x** as the library. When the per-form roll-out actually started in Phase 21,
> the conflict became clear: PropManager is an Inertia app, and Inertia's
> `useForm` *already* owns form state, submission, `processing`, and the error
> channel (`form.errors`) every template reads. vee-validate's `useForm`/
> `useField` is a *second* form-state engine — adopting it means two sources of
> truth for "what's in the form" and "what's wrong with it", reconciled by hand
> on every form. That is the long-term debt trap.
>
> The value Phase-15 actually wanted — type-safe, server-mirrored, inline
> client-side validation — comes from the **Zod schema**, not from vee-validate.
> So Phase 21 keeps the schemas and drops the second form engine. vee-validate
> remains the right tool for a *non-Inertia* Vue app; it is not the right tool
> here.

Reasons for Zod:
- Type-safe schemas that mirror the server-side Form Request rules
- Aligns with the rest of the TS ecosystem
- No competing form-state engine — Inertia's `useForm` stays the single owner
- `~18KB` gzipped (shared `schemas` chunk, lazy-loaded with the forms)

## Security stance

**Frontend validation is UX, not security.** Every field validated client-side MUST be re-validated server-side. The Vue layer can:

- Disable submit buttons on invalid state
- Show inline `aria-invalid` + error messages
- Pre-validate before network round-trip (UX win)

The Vue layer MUST NOT:

- Be the only enforcement of a length / format / business rule
- Be trusted to mask sensitive fields (the server still receives them)
- Use validation rules different from the server's

## Recommended structure

One Zod schema per form, under `resources/js/composables/forms/schemas/`:

```ts
// composables/forms/schemas/registerSchema.ts
import { z } from 'zod';

export const registerSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    email: z.string().email().max(255),
    password: z.string().min(8, 'Password must be at least 8 characters'),
});
```

Server-side rules live in the matching Form Request (e.g.
`App\Http\Controllers\Auth\RegisteredUserController::store`) and MUST match the
Zod schema. A divergence is a bug. Server-only rules — `unique:`, `exists:` —
cannot be mirrored client-side and stay server-authoritative.

The form component bridges the schema to Inertia's `useForm` via `useZodForm`:

```ts
import { useForm } from '@inertiajs/vue3';
import { useZodForm } from '@/composables/forms/useZodForm';
import { registerSchema } from '@/composables/forms/schemas/registerSchema';

const form = useForm({ name: '', email: '', password: '' });
const { validate } = useZodForm(form, registerSchema);

const submit = () => {
    if (!validate()) return;          // Zod failures land in form.errors
    form.post(route('register'));     // server re-validates regardless
};
```

`validate()` runs the schema against `form.data()`, pipes any failure into
`form.errors` via `setError()`, and returns `false` to abort the post. The
template's existing `<InputError :message="form.errors.x">` / `form.errors.x`
display works unchanged — a client (Zod) error and a server (422) error look
identical to the template.

## Roll-out status

- **Phase 15**: pattern doc + library decision (originally vee-validate).
- **Phase 21 DEFER-FRONT-2**: decision corrected to Zod + `useZodForm` (see the
  update box above). Adopted on the first 5 forms — `Auth/Login`,
  `Auth/Register`, `Leases/Create` (tenant invitation), `Tickets/Create`,
  `MoveOuts/Create` — with server-mirrored schemas.
- **Future**: incrementally migrate the remaining forms on-touch. Order:
  Tenant onboarding → Landlord settings → Finance forms → everything else.
  The form-by-form migration is the lion's share of the work and is
  intentionally incremental — the goal is a consistent pattern, not a
  big-bang conversion.

## Cross-references

- `resources/js/composables/forms/useZodForm.ts` — the schema → Inertia bridge
- Phase-15 FRONT-8: Inertia share() audit — same broader theme of frontend-trust boundaries
- Phase-3 VALID-* findings: server-side validation rules — must remain authoritative
