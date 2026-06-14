# Kenyan Legal & Regulatory Compliance — PropManager

Reference for the Agreements/Legal layer and the money model. Companion to
[`payments-hub-rebuild-foundation.md`](payments-hub-rebuild-foundation.md).

> **Not legal advice.** This is engineering research grounded in primary Kenyan
> statute (kenyalaw.org) and reputable secondary analysis, compiled to *design
> to the law* and to scope a tight advocate review (see
> [`legal-review-brief.md`](legal-review-brief.md)). Section numbers and rates
> are cited; items flagged ⚠️ need confirmation against live statute/Gazette at
> go-live. Jurisdiction: Kenya. Currency: KES.

---

## 1. Money model & client funds — split-at-source is the default

**Binding law:** Estate Agents Act (Cap 533); Estate Agents (Accounts) Rules 1989
(LN 20/1989); National Payment System Act 2011 + NPS Regulations 2014 (LN 109/2014).

- Client money received by an agent is held **on trust**. The Accounts Rules bite
  the moment the manager **receives** client money: separate **"client"/"trust"**
  account (Rule 2), pay in without delay (Rule 5), **no commingling / no over-draw
  per beneficiary** (Rule 11), fee drawn only by explicit payable-to-agent event
  (Rule 12), per-client books kept **7 years** (Rules 14–15).
- If **PropManager itself** pooled/aggregated float, that likely makes it a
  **payment-service provider** under the NPS Act → CBK licensing + customer funds
  in trust at a regulated bank. **Avoid.**

**Design requirements**
- **Split-at-source (Paystack subaccounts) is the DEFAULT.** Owner net routes
  directly to the owner; only the fee lands with the manager → the manager never
  "receives client money" → the Accounts-Rules trust regime and the CBK PSP risk
  largely fall away.
- **Collect-then-remit (float) is supported but GATED:** ledger-level per-owner
  segregation (Rule 11 in code), a named trust account, a reconciliation engine
  (bank balance = Σ per-owner ledger), explicit fee-draw events, no commingling.
- Funds always move through a **licensed PSP** (Paystack/IntaSend/M-Pesa); float,
  where unavoidable, sits in the **manager's** named trust account — never a
  PropManager-pooled account.
- A deposit / maintenance-reserve / arrears wallet **is** client money — route it
  split-at-source too, or treat it under the remit controls.

**Go-live gates:** ⚠️ CBK-PSP/positioning opinion *if* the remit model ships; the
"neutral technology host" posture holds only while PropManager never custodies
money and is not the contracting agent.

---

## 2. Manager regulation — gate onboarding

**Binding law:** Estate Agents Act (Cap 533) s.2 (definition incl. *management*),
s.13 (only individual citizens register), s.18 (mandatory registration;
firms practise via registered directors), s.18(2) (penalty: **KES 1M individual /
5M legal person**), s.19 (indemnity bond **KES 200,000 × principals**); POCAMLA
2009 + AML/CFT (Amendment) Act 2023 (real-estate agents are FRC reporting
institutions: CDD/KYC, STR, CTR > **KES 1M**, 5-yr records).

**Design requirements**
- Manager entity: **required, verified** EARB registration number + practising-cert
  expiry; **block fee-earning management onboarding until present and valid**.
- Capture the **s.19 indemnity bond** (insurer, amount ≥ 200k × principals, expiry).
- **CDD/KYC** on owners and managers (ID, KRA PIN, beneficial owner for corporates);
  STR escalation path; CTR trigger at KES 1M for cash-recorded payments.
- Retention: financial records **7 years** (Estate Agents beats POCAMLA's 5).

**Go-live gates:** ⚠️ written opinion on whether the AML regime bites on
rent-management (FRC guidance is sale/purchase-focused; build CDD conservatively).

---

## 3. Tax — a real subsystem; eTIMS is a hard gate

**Binding law:** Income Tax Act (Cap 470) — MRI (s.6A) + WHT/agents (s.35);
VAT Act 2013 (Cap 476); Tax Procedures (Electronic Tax Invoice) Regulations 2024.

| Tax | Rule | Notes |
|---|---|---|
| **MRI** | **7.5%** of gross resident residential rent | Band KES 288k–15M/yr; final; filed by the 20th. Appointed agent remits within **5 working days**. |
| **VAT** | **16%** on the management **fee** | Only if the manager is **VAT-registered** (turnover ≥ KES 5M). Residential rent is **exempt**; commercial rent standard-rated. |
| **WHT** | **5%** on a resident manager fee | De-minimis KES 24,000/month. Non-resident fee 20%. |
| **Rent → non-resident owner** | **30% final** | Payer is a withholding agent by law (ITA s.35) — a hard, separate branch. |
| **eTIMS** | Fee invoice needs CU number + QR | A self-printed receipt is **not** a valid/deductible tax invoice. |

**Design requirements**
- Manager: VAT-registered flag + PIN → fee line shows 16% VAT **only when registered**.
- Owner statement math: **gross collected − MRI (if appointed agent) − fee (+VAT)
  − WHT-on-fee = net owner payout.**
- Two remittance cadences (appointed-agent 5 working days vs landlord-self 20th).
- Non-resident owner = hard branch (30% final, no MRI 7.5%, no eTIMS on rent).
- **eTIMS integration** (OSCU/VSCU/API) for fee/VAT invoices; **eRITS** adapter as
  the rental channel; VAT rate + KES 5M threshold are **config**, not constants.

**Go-live gates:** eTIMS live for fee/VAT invoices (**hard blocker**); ⚠️ advisor
sign-off on (a) management-fee VAT status, (b) landlord-rent eTIMS/eRITS position,
(c) appointed-agent status; re-confirm rates against the Finance Act in force.

---

## 4. E-signature & contract validity — two tracks

**Binding law:** KICA (Cap 411A) s83C (electronic = writing), s83J (e-contracts
valid), s83O (e-signature), s83P (advanced e-signature), s83B (exclusions: wills,
negotiable instruments, documents of title); Business Laws (Amendment) Act 2020;
Law of Contract Act (Cap 23) s3(3) (land-disposition: writing + signed + **witness
attestation**), s3(6) ("sign" includes advanced e-signature); Evidence Act
s78A/s106B (admissibility); Land Registration Act 2012 s54 + LN 130/2020.

**Design requirements**
- **Track A (volume):** management agreements + short-term tenancies (**≤2 yr /
  periodic, Land Act §58**) → simple e-signature + OTP + audit bundle is **legally
  sufficient**; no registration.
- **Track B (gated):** leases **>2 yr** are registrable (LRA §54) → **CA-licensed-CSP
  advanced signature** or the LN 130/2020 **print-attest-scan** path. Flag these and
  hand off — a click-assent flow does **not** produce a registrable instrument.
- Self-hosted **Documenso/DocuSeal** PKCS#12 = strong tamper-evidence integrity
  layer for everything, but **not** a Kenyan-licensed-CSP advanced signature.
  *Selected tool: **Documenso*** — run unmodified as a separate self-hosted service
  (its own Postgres) via the v2 API + signed webhooks + embedded Vue signing; we
  supply the `.p12`. AGPL stays off PropManager's code (separate service). It is the
  Track-A integrity layer, **not** a substitute for Track-B registrable-lease
  formalities. (See the tooling-decisions record.)
- **Always capture the evidentiary bundle:** intent affirmation, consent to transact
  electronically, OTP-bound identity, server timestamp, IP/device, **SHA-256
  document hash**, append-only audit trail, completion certificate; be **s106B(4)**-ready.
- **Never** offer e-sign for the s83B excluded categories.

**Go-live gates:** ⚠️ LoCA **s3(3) witness-attestation** for remote signing is
*genuinely unsettled* — advocate opinion before relying on it for tenancies; ⚠️
confirm the registrable-lease threshold section + current CA-licensed CSP roster.

---

## 5. Tenancy law

**Binding law:** Rent Restriction Act (Cap 296, controlled premises only — old
KES 2,500/mo ⚠️ threshold means most modern lets are *uncontrolled*); Land Act 2012
(§57 periodic-tenancy notice, §58 short-term leases, §152A–H eviction/relief);
Distress for Rent Act (Cap 293); Law of Contract Act s3(3); Civil Procedure Rules
Order 5 r22B/r22C (service via email / mobile messaging incl. WhatsApp); Evidence
Act s106B (electronic-record admissibility certificate).

**Design requirements**
- **Self-help is criminal (Cap 296 s.29)** — never build lock-out / goods-seizure /
  utility cut-off actions. Provide a **lawful-recovery guided path** (demand →
  statutory notice → tribunal/ELC order → court-executed possession).
- Notice engine: periodic monthly tenancy → **≥1 month ending on a rent day**
  (Land Act §57(3)); store **served-notice proof** (content snapshot, channel,
  recipient, timestamp, delivery/read evidence, computed effective date).
- **Proof of service** (Civil Procedure Rules Order 5 **r22B** email / **r22C**
  WhatsApp/messaging): service is **deemed effected on the delivery receipt**, so the
  notice engine must persist the **delivery receipt** (SMS/email/WhatsApp — already
  available via Africa's Talking + mail + the official WhatsApp BSP) as an
  **affidavit-of-service-ready** record. The legal test is provable **delivery**, not
  that the recipient *opened/read* it — so view-tracking adds no legal weight (and the
  electronic record needs an **Evidence Act s106B** certificate to be admissible).
- Rent change = a **versioned amendment** requiring notice + tenant **re-assent**;
  never mutate `Lease.rent_amount` in place.
- Deposit cap (1–2 months) and return window (14–30 days) are **contractual norms,
  NOT statute** for uncontrolled tenancies — present as defaults/guidance, never as
  legal requirements. Controlled units: block premium/"key money" (Cap 296 s.17).
- Lease classifier on creation: `tenancy_type` (≤2yr short-term vs >2yr registrable),
  `is_controlled`, `requires_registration`.

**Go-live gates:** ⚠️ advocate-approved notice/termination/rent-increase templates;
confirm default-uncontrolled handling + the exact registrable threshold.

---

## 6. Data protection

**Binding law:** Data Protection Act 2019 (No. 24); General Regs (LN 263/2021);
Registration Regs (LN 265/2021); Compliance & Enforcement Regs (LN 264/2021); ODPC.

- **Registration is mandatory regardless of size** — "property management" and
  "financial services" are in the Third Schedule that **disapplies** the small-entity
  exemption (s.18; LN 265 Reg. 13). PropManager = **controller AND processor**;
  customers must register independently. Fees KES 4k/16k/40k by tier; cert valid 24 mo.
- **"Property details" is statutorily SENSITIVE** (s.2) — most of our data is sensitive.
- **DPIA 60 days pre-launch** (s.31) — the Huduma/Maisha digital-ID rollouts were
  struck down for a missing DPIA; treat as a precondition.
- Breach notice **≤72 h** to ODPC (s.43); DSAR SLAs **7/14/14 days** (access/rectify/
  erase); penalties to **KES 5M / 1% turnover** (s.63).

**Design requirements**
- Per-purpose lawful basis (contract for lease/rent core; consent for marketing/
  optional/cross-border) — granular, unbundled consent toggles at onboarding with
  versioned proof; layered privacy notice.
- DSAR module with statutory SLA timers (access 7d, rectify 14d, erase 14d, objection,
  portability export).
- Retention schedule + scheduled anonymise/delete job; encrypt IDs/bank details
  (done) + agreement blobs + assent records (assent IP/device = PII).
- Breach runbook with ODPC-notification templates; **DPIA** for ID + payments +
  e-sign processing; vendor DPAs + cross-border safeguards (s.48/49) for AWS/e-sign.

**Go-live gates:** ODPC registration filed; DPIA completed/submitted; consent +
privacy notice live; DSAR workflow operational; breach runbook owned; vendor DPAs
signed. ⚠️ controller-vs-processor-vs-joint mapping needs a privacy-lawyer call.

---

## 7. Consolidated go-live gate checklist

**Engineering (I build):** split-at-source default + remit segregation/reconciliation;
manager EARB/bond/AML/KYC capture+gating; tax engine (MRI/VAT/WHT) + statement math;
**eTIMS integration**; two-track e-sign + evidentiary bundle; notice engine +
served-proof; lawful-recovery path (no self-help); lease classifier + time-versioned
amendments; DPA — consent/DSAR/retention/breach-runbook/encryption; **DPIA** document.

**Registrations/filings (operational):** ODPC (controller + processor); manager EARB
+ FRC; eTIMS/eRITS onboarding; vendor DPAs (AWS, e-sign); hosting-region decision.

**Advocate sign-off (the thin residual):** see [`legal-review-brief.md`](legal-review-brief.md).

---

## 8. Primary sources

Estate Agents Act Cap 533 — https://new.kenyalaw.org/akn/ke/act/1984/17 ·
Accounts Rules LN 20/1989 — https://new.kenyalaw.org/akn/ke/act/ln/1989/20 ·
NPS Act 2011 — https://www.centralbank.go.ke/national-payments-system/ ·
KRA MRI — https://www.kra.go.ke/individual/filing-paying/types-of-taxes/residential-rental-income ·
PwC Kenya tax summaries — https://taxsummaries.pwc.com/kenya ·
eTIMS Regs 2024 (RSM) — https://www.rsm.global/kenya/news/kenya-tax-alert-tax-procedures-electronic-tax-invoice-regulations-2024 ·
KICA Cap 411A — https://kenyalaw.org/kl/fileadmin/pdfdownloads/Acts/KenyaInformationandCommunicationsAct(No2of1998).pdf ·
Law of Contract Act Cap 23 — https://new.kenyalaw.org/akn/ke/act/1960/43 ·
Land Act 2012 — https://www.parliament.go.ke/sites/default/files/2017-05/LandAct2012.pdf ·
Land Registration Act 2012 — https://new.kenyalaw.org/akn/ke/act/2012/3 ·
LN 130/2020 — https://new.kenyalaw.org/akn/ke/act/ln/2020/130 ·
Rent Restriction Act Cap 296 — https://new.kenyalaw.org/akn/ke/act/1959/35 ·
Data Protection Act 2019 — https://new.kenyalaw.org/akn/ke/act/2019/24 ·
DPA General Regs LN 263/2021 — https://new.kenyalaw.org/akn/ke/act/ln/2021/263 ·
DPA Registration Regs LN 265/2021 — https://new.kenyalaw.org/akn/ke/act/ln/2021/265 ·
ODPC — https://www.odpc.go.ke/ ·
Civil Procedure Rules (Order 5 service incl. r22B/r22C) — https://new.kenyalaw.org/akn/ke/act/ln/2010/151 ·
Evidence Act Cap 80 (s106B) — https://new.kenyalaw.org/akn/ke/act/1963/46
