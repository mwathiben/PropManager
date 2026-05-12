# Privacy Notice — Point-of-Collection Template

Phase-13 DPA-9: authoritative disclosure text. Kenya DPA Section 29 / GDPR Article 13 require that at the point of collecting personal data, the data subject is informed of:

1. The identity of the data controller
2. The purposes of the processing
3. The lawful basis (Kenya DPA Section 30 / GDPR Article 6)
4. The recipients or categories of recipients
5. International transfers and their safeguards
6. The retention period
7. The data subject's rights (access, rectification, erasure, restriction, objection, portability)
8. The right to withdraw consent (where consent is the basis)
9. The right to lodge a complaint with the ODPC

## Audit gate

For each of the major collection forms, the in-repo notice MUST link to this template:

| Form | Location | Disclosure status |
|------|----------|-------------------|
| Registration | `resources/js/Pages/Auth/Register.vue` | needs link to `/legal/privacy-policy` + acknowledgement checkbox |
| Tenant onboarding | `resources/js/Pages/Tenant/Onboarding.vue` | needs link |
| KYC submission | `resources/js/Pages/Tenants/Kyc/Submit.vue` | needs link |
| Payment configuration | landlord-side; collects credentials, NOT subject PII | exempt |

If a collection form does not present the privacy notice + record an acknowledgement Consent row, that is a Section 29 / Article 13 gap.

## Disclosure text (default — operator may extend)

```text
PropManager — Privacy Notice (effective 2026-05-12)

1. Data Controller
   PropManager Ltd, registered in Kenya. ODPC registration:
   <KENYA_DPA_REGISTRATION env value>. Contact: <support@email>.

2. What we collect
   - Account: name, email, phone, password (hashed)
   - Tenant: lease, payments, KYC documents (national_id, photos)
   - Operational: IP, user-agent, session cookies, audit log

3. Why we collect it (lawful basis — Section 30 / Article 6)
   - To perform our contract with you (lease management, payment
     processing) — basis: contract
   - To meet our legal obligations (tax records, AML/CFT) —
     basis: legal_obligation
   - For analytics & service improvement where you have opted in —
     basis: consent

4. Who we share with
   - Payment gateways (M-Pesa, Paystack, IntaSend, your landlord's
     bank) to process payments
   - Our hosting providers (AWS) for infrastructure
   - Our observability provider (Sentry) for error tracking — only
     to capture errors, never your sensitive personal data; PII in
     logs is masked at capture (DPA-6)

5. International transfers
   Some of our infrastructure may be hosted outside Kenya. We rely
   on adequate-protection jurisdictions (EU, UK, Canada) or
   Standard Contractual Clauses where adequate protection is not
   established. See `KenyaDpaService::canTransferCrossBorder` for
   our adequate-protection list.

6. How long we keep your data
   - Tenant + lease data: 7 years after lease termination (tax law)
   - Payment records: 7 years (financial regulations)
   - Audit logs: 1 year (operational) / 7 years (financial)
   - Marketing consent: until you withdraw it
   - Withdrawn consent records: 3 years (proof of withdrawal)
   - Security incidents: 10 years

7. Your rights
   - Access: download via Settings → Privacy → Export
   - Erasure: request via Settings → Privacy → Delete
   - Rectification: edit your profile or contact support
   - Restriction (Article 18 / Section 26(d)): Settings → Privacy → Restrict Processing
   - Portability (Article 20): Export above
   - Objection (Article 21): Settings → Privacy → Object — for
     legitimate-interests processing only

8. Withdrawing consent
   For any consent-based processing (marketing, analytics, profile
   enrichment), withdraw via Settings → Privacy → Consent History.
   Withdrawing terms/privacy consent requires account deletion.

9. ODPC contact
   You may lodge a complaint with Kenya's Office of the Data
   Protection Commissioner at info@odpc.go.ke or
   https://www.odpc.go.ke.

10. Changes
    We notify you of material changes via in-app notice and email.
    Previous versions remain accessible via your consent history.
```

## Source of truth

This template is the authoritative copy. The user-facing route `/legal/{type}` (handled by `ConsentController::view`) renders the operator-edited copy stored in `legal_documents`. If those diverge, the operator-edited copy wins for active sessions; this template is the baseline for what the operator copy MUST cover.

When updating, ALSO update:

- `app/Services/KenyaDpaService::getRetentionRequirements()` if retention windows change
- `app/Services/KenyaDpaService::canTransferCrossBorder()` if adequate-protection list changes
- `app/Models/Consent::TYPE_*` if new consent categories are introduced

## DPIA marker

If a change to this notice adds processing in a new
SENSITIVE_DATA_CATEGORIES area (national_id, ethnic_origin,
health_data, biometric_data, genetic_data, sex_life,
sexual_orientation, political_opinion, religious_belief,
trade_union_membership, criminal_record), generate a DPIA via
`KenyaDpaService::generateDpiaTemplate()` and attach it to the PR.
See `docs/legal/dpia-process.md` for the gate.
