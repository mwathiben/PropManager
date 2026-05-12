# Frontend Form Validation — Convention

Phase-15 FRONT-7: PropManager's Vue forms today rely 100% on server-side validation. The server is the only line of defence — which is the correct security posture — but the UX is poor (every validation failure is a 422 redirect with flash messages, no inline field-level errors). This document pins the pattern for adding inline validation when needed, without weakening the server as the source of truth.

## Pinned library

**vee-validate 4.x** (https://vee-validate.logaretm.com/v4/). Reasons:
- Vue 3 composition-API native (`useField`, `useForm`)
- Schema validation via Yup or Zod (Zod aligns with the rest of the TS ecosystem)
- Small bundle (~12KB gzipped) — comparable to writing custom composables
- Active maintenance, no peer-dependency hell

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

```ts
// composables/useFormSchema.ts
import { z } from 'zod';

export const registerSchema = z.object({
    name: z.string().min(2).max(80),
    email: z.string().email().max(255),
    password: z.string().min(12).max(128),
});
```

Server-side rules live in `App\Http\Requests\Auth\RegisterRequest` and MUST match the Zod schema. A divergence is a security bug.

## Roll-out plan

1. **Phase 15 (now)**: ship this doc + Zod + vee-validate as a `dependencies` (npm). Pick ONE form as the canonical example (`resources/js/Pages/Auth/Register.vue`).
2. **Phase 15 follow-up**: add a CI grep that warns when a new Vue page defines a `<form>` without `useForm()`. Non-blocking; reminds developers to think about it.
3. **Phase 16+**: incrementally migrate the remaining forms. Order: Auth → Tenant onboarding → Landlord settings → Payment forms → everything else.

The form-by-form migration is the lion's share of the work; deferring it past Phase 15 is intentional — the goal is to establish the pattern, not the wholesale conversion.

## Cross-references

- Phase-13 DPA-1: Consent withdrawal — the only client-validated form today (Inertia form helper, not vee-validate)
- Phase-15 FRONT-8: Inertia share() audit — same broader theme of frontend-trust boundaries
- Phase-3 VALID-* findings: server-side validation rules — must remain authoritative
