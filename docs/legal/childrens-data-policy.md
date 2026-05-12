# Children's Data Policy

Phase-13 DPA-10: Kenya DPA Section 33 / GDPR Article 8 require additional consent (parental authorisation) for processing personal data of children under 18 (Kenya) / 16 (EU GDPR default; 13 in some Member States).

## Policy

**PropManager does not knowingly accept tenants under 18.** The product is B2B SaaS for landlords; landlords are adults by definition (registration requires KRA PIN), and the typical tenant base is over 18.

However, the architecture allows a landlord to onboard a tenant of any age. The policy is:

1. **At KYC submission**, the `tenant_dob` field (when present) MUST be passed through `KenyaDpaService::isMinor()`.
2. **If the result is true**, the submission is rejected unless the landlord supplies a `parental_consent_artefact_url` referencing an out-of-band parental-consent document.
3. **Where a minor tenant is accepted**, the parental consent reference is stored in `TenantKycSubmission.metadata` and surfaced to the regulator on request.

## Implementation status

- ✅ `KenyaDpaService::isMinor(string $dob)` helper shipped (Phase-13 DPA-10).
- ⚠️ `tenant_dob` is **not yet a TenantKycSubmission column**. Submissions today do not capture DOB; the helper is dormant until the schema and form are extended. This is intentional — the PRD calls this "a policy-and-form question, not a deep code change."
- ⚠️ `parental_consent_artefact_url` is not yet a TenantKycSubmission column. Same reasoning.

## When to wire the gate

Adopt the full flow when any of these triggers fire:

- A landlord onboards a tenant who is verifiably under 18 (operator escalation)
- A landlord segment serving student housing is added (architectural change)
- A regulator audit asks for evidence of Section 33 compliance

At that point: add the migration, the form field, the `isMinor()` gate, and the parental-consent intake. The helper is ready to consume.

## Fail-safe behaviour

`isMinor()` returns `true` for unparseable date strings — fail-safe means a malformed DOB blocks acceptance and forces operator review. This avoids the risk of silently accepting a malformed-DOB submission that bypasses the policy gate.

## Cross-references

- Section 33 of the Kenya Data Protection Act 2019
- Article 8 of the GDPR (children's consent)
- `KenyaDpaService::isMinor()` — the gate function
- `docs/legal/privacy-notice-template.md` — the disclosure for parental consent (when relevant)
