# Payments Hub — Finalization Roadmap

The canonical path to a world-class, fully-finalized Payments Hub. Supersedes the
looser "L0–L4 + Phase 3" framing. Companions:
[`payments-hub-rebuild-foundation.md`](payments-hub-rebuild-foundation.md) (roles,
tenancy, money flow, onboarding), [`legal-compliance-kenya.md`](legal-compliance-kenya.md)
(statute-grounded requirements + go-live gates), [`tooling-decisions.md`](tooling-decisions.md).

---

## 0. The contract (so we never ship dormant code again)

> **A slice is not "done" until it is live, reachable in the UI, wired end-to-end,
> tested (including cross-tenant and money-correctness), and compliant. No layer
> ships dormant.**

Every item below is a **vertical slice** — schema → service → wiring → UI → tests →
live — not a horizontal layer awaiting a future connector. A building block (e.g. the
fee calculator) is **not** counted as a shipped feature; it ships live *inside* the
slice that exercises it. Nothing is marked complete in a disconnected state.

**Decision (adopted):** the current dangling threads — the dormant fee engine and the
un-hardened WhatsApp channel — are **folded into their real slices** (fee engine →
Slice 2; WhatsApp → Slice 3). We do **not** ship an intermediate "wired but not
exercised" Slice 0.

---

## 1. Definition of world-class — the bar (all must be green)

1. **Reconciles to the cent** across M-Pesa / Paystack / IntaSend / bank / cash — every
   shilling tied to invoice → owner → agreement.
2. **Every governed term is agreement-backed and locked** — no fee/rent/payout change
   without the counterparty re-signing.
3. **Every notice is provable** — delivery receipt persisted (CPR Order 5 r22C),
   affidavit-ready.
4. **Every statement & receipt is tax-correct** — MRI / VAT / WHT computed,
   eTIMS-conformant.
5. **Every agreement is e-signed, tamper-evident, audit-trailed** (Documenso + the
   evidentiary bundle).
6. **Every role sees exactly its truth** — tenant-scoped, fail-closed, no leakage.
7. **Launch-legal in Kenya** — EARB/AML gated, DPA-registered, DPIA filed,
   advocate-signed clauses.
8. **Money-flow honest** — split-at-source default; remit only behind trust-account
   discipline.
9. **No dormant / unreachable code** — every merged capability is exercised in the UI
   and by tests.
10. **Premium feel** — performance, a11y, i18n (en/sw/ar incl. legal text), real
    empty/loading/error states.

---

## 2. Where we are

**Live:** roles/tenancy (Phase 1), onboarding context (2a), payment-config home + Hub
shell (#72–76), channels (M-Pesa/Paystack/IntaSend/SMS/WhatsApp-via-Twilio/push/mail),
owner ledger/payout/statement (fee = **collected-only placeholder**).

**Dormant / dangling (to be absorbed, not left):** the **fee engine** (calculator
called by nothing; new base/cadence columns unused; statement still collected-only),
**WhatsApp** (fallback not fail-closed, no opt-in/proof-of-service), **no agreement
layer**, **no tax/eTIMS**, **no system-wide reconciliation**, **Hub shell is not yet a
real cockpit**.

---

## 3. The slices

| # | Slice | Live outcome |
|---|---|---|
| 1 | Assent spine + Platform ToS | Every user signs the current ToS before operating; re-assent on new versions. |
| 2 | Management agreement → fee & money-flow live | Manager composes agreement → owner e-signs → fee engine **wakes up** (set, applied, locked, netted, rendered); split-vs-remit chosen with disclosure. |
| 3 | Collections cockpit | See collection health and *act* — remind/request/record/initiate across rails + channels; tenant gets an eTIMS receipt. |
| 4 | Payouts & owner oversight | Owner net flows out (split or remit); owner portal shows collection, signed terms, tax-correct statements, payouts, dispute path. |
| 5 | System-wide reconciliation | Gateway + bank + owner-remittance reconciled to the cent; config-vs-agreement drift detection. |
| 6 | Tenancy agreements | Leases signed & governed (rent/deposit/late-fee/water/notice as locked clauses); notice engine with proof-of-service; move-out condition photos → deposit reconciliation. |
| 7 | Compliance close-out (go-live gate) | ODPC registration, DPIA, consent + DSAR, retention, breach runbook; eTIMS/eRITS live; EARB/AML gated; advocate sign-off; signed-PDF + audit. |
| 8 | Coherence & polish | One cockpit per role — performance, a11y, i18n (incl. legal text), premium UX; #72 shell retired into the finished hub. |

### Slice 1 — Assent spine + Platform ToS
- **Builds & wires:** immutable, versioned, **hashed** assent record (the evidentiary
  bundle: intent, consent, OTP-bound identity, timestamp, IP/device, doc hash,
  completion cert); in-house click-assent + OTP; **Documenso** stood up as the signing
  service; the **ToS gate** at signup/entry *using* it; re-assent on version change.
- **Compliance baked:** assent records treated as PII (DPA); platform-neutrality posture
  live (liability shield).
- **DoD:** users actually sign; gate blocks the un-assented; re-assent works; tested.

### Slice 2 — Management agreement → fee & money-flow go live *(absorbs the dormant fee engine)*
- **Builds & wires:** clause model + composer (management clauses: fee [uses the 2b-i
  engine + per-unit occupancy], money-flow [split vs remit], payout destination, manager
  authority, non-removable platform-neutrality); manager invites owner; owner **e-signs
  at onboarding** (Documenso, embedded Vue); `AgreementApplicator` **writes + locks**
  `PropertyOwner.management_fee_*`; `OwnerStatementService` **rewired to
  `ManagementFeeCalculator`** with a real `FeePeriodContext` (collected ✓ + **billed**
  from invoices + **scheduled** from lease rent + **occupiedUnits** from leases); fee
  rendered in statement + hub + netted into payout; amendment loop (re-assent).
- **Compliance baked:** manager onboarding **gated on EARB reg# + s.19 bond + AML/KYC**;
  split-at-source default disclosed with implications.
- **DoD:** the fee model is exercised **end-to-end** (no dormant calculator); governed
  config locked; cross-tenant + money-correctness tests; behavior-preserving for
  existing (`none`/collected) data.

### Slice 3 — Collections cockpit *(absorbs WhatsApp hardening + eTIMS receipts)*
- **Builds & wires:** Hub renders collection health (collected/billed/scheduled, arrears
  aging) on the now-live figures; operate — remind (WhatsApp/SMS/email/push), request
  (payment links), record manual, initiate STK/Paystack/IntaSend.
- **Folds in:** WhatsApp **fail-closed** fallback, **opt-in consent** capture, **delivery
  webhooks → proof-of-service**; **eTIMS-conformant receipts** on payment.
- **DoD:** every channel live with proof-of-delivery; receipts eTIMS-valid; tested.

### Slice 4 — Payouts & owner oversight
- **Builds & wires:** split-at-source execution (Paystack subaccounts) + collect-then-remit
  (`OwnerPayout`, trust-account discipline); owner portal — portfolio collection, signed
  terms (the agreement), tax-correct statements (MRI/VAT/WHT lines), payouts, dispute path.
- **DoD:** both payout modes live + reconciled; owner sees agreement-governed truth; tested.

### Slice 5 — System-wide reconciliation
- **Builds & wires:** gateway + bank + owner-remittance reconciliation to invoice → owner
  → agreement; **drift detection** (governed config vs agreement); Hub reconciliation view;
  discrepancy resolution.
- **DoD:** reconciliation across rails live; drift-lock enforced; tested.

### Slice 6 — Tenancy agreements *(absorbs media library + notice engine)*
- **Builds & wires:** tenancy clause set (rent/deposit/late-fee/water/notice) → compose →
  tenant **e-signs at onboarding** → apply + **lock** to `Lease`; **notice engine** with
  **proof-of-service** (delivery receipts); **move-out condition photos** (Laravel media
  library + EXIF strip + signed URLs) → deposit reconciliation. Lawful-recovery guided path
  (no self-help — Cap 296 s.29).
- **DoD:** lease config agreement-locked; notices provable; deposit evidence captured; tested.

### Slice 7 — Compliance close-out (go-live gate)
- **Closes:** ODPC registration (controller + processor); **DPIA** filed; consent +
  **DSAR** module (7/14/14-day SLAs); retention/auto-delete; breach runbook; **eTIMS/eRITS**
  live; EARB/AML fully gated; **advocate sign-off** on the clause library + the
  [`legal-review-brief.md`](legal-review-brief.md) items; signed-PDF + assent audit.
- **DoD:** every go-live gate in `legal-compliance-kenya.md` §7 closed.

### Slice 8 — Coherence & polish
- **Builds:** the Hub as one cockpit per role; performance; a11y; i18n (en/sw/ar incl. legal
  text); empty/loading/error states; retire the #72 shell into the finished hub.
- **DoD:** the §1 bar, all green.

---

## 4. Parallel tracks (woven, not a final scramble)

- **Compliance/legal** starts at Slice 1: kick off **ODPC registration**, the **DPIA**, and
  the **advocate review** (brief already written) so they close by Slice 7 rather than block
  launch. **Tax** (MRI/VAT/WHT/eTIMS) lands *inside* Slices 3–4 where money documents are
  produced.
- **Documenso** self-hosted service stood up in Slice 1, reused by Slices 2 & 6.
- **Notifications** (WhatsApp official path) hardened in Slice 3, reused by every
  notice/receipt thereafter.

---

## 5. Execution discipline

- Each slice = 1–3 PRs, characterization-test rigor, **prototype → review** for user-facing
  slices, serialize-on-predecessor-CI merge cadence; full-suite Tests job is the gate (the
  inherited RTL Visual Snapshots failure is non-required — merge past it).
- **Definition of done for the whole programme = the §1 ten-point bar, all green.**
