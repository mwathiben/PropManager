# Advocate Review Brief — PropManager Agreements/Legal Layer

For a Kenyan advocate. The engineering team has researched the framework and built
to it (see [`legal-compliance-kenya.md`](legal-compliance-kenya.md)). This brief
asks for **review and sign-off on a defined set of points** — not open research.
Each item gives the provision in question and the specific question to answer.

---

## A. Document/contract review

1. **Clause library wording.** Review the drafted Kenyan management-agreement and
   tenancy-agreement clauses (financial, term, responsibilities, conduct,
   termination, dispute, platform-neutrality) for legal soundness and enforceability.
   *Deliverable: redlined, approved clause text per jurisdiction = Kenya.*
2. **Notice templates.** Approve the wording of notice-to-quit, termination, and
   rent-increase notices per tenancy type (controlled vs uncontrolled; Land Act
   §57(3) periodic notice; Cap 296 §15/§11).
3. **Platform ToS + Privacy Policy.** Draft/approve the platform's own user terms,
   including the **neutral-host / not-a-party / not-liable-for-breach** posture and
   the *"informational, not legal advice"* framing.

## B. Specific legal questions (genuinely unsettled — we need a position)

4. **E-signature for tenancies (LoCA s3(3) vs s3(6)).** The 2020 amendment lets an
   *advanced* electronic signature satisfy "sign," but the **witness-attestation**
   requirement in s3(3)(b) was not removed. *How is "a witness present when signed"
   satisfied for a remote advanced-electronic signing? Is a witness step required,
   or does the advanced e-signature alone discharge attestation?*
5. **Registrable-lease threshold.** Confirm the exact LRA section + term threshold at
   which a residential lease becomes a registrable interest (sources gave "over 2
   years" for binding third parties; "over 21 years" for a certificate of lease), so
   the Track A/Track B branching is correct.
6. **CA-licensed CSP.** Confirm the current roster of Communications-Authority-licensed
   certification service providers, so we don't over-claim "advanced electronic
   signature" for land instruments.

## C. Regulatory positioning

7. **CBK / payments (only if the remit/float model ships).** Does whoever holds the
   float need a CBK PSP licence / formal trust structure under the NPS Act 2011? Does
   routing through a licensed PSP into the **manager's** named trust account (never a
   PropManager-pooled account) keep PropManager out of scope?
8. **Estate-agency positioning.** Will PropManager be deemed to "carry on
   estate-agency business" (Cap 533 s.2) despite the neutral-host framing? Confirm the
   conditions under which the framing holds (no custody of money, not the contracting
   agent, not performing the s.2 intermediary acts itself).
9. **AML scope.** Does the FRC/POCAMLA regime bite on ongoing **rent management**, or
   mainly on sale/purchase transactions? (We build CDD conservatively regardless.)

## D. Tax confirmations (with a tax advisor)

10. Confirm: (a) management-fee **VAT** status (vatable service); (b) the
    **landlord-rent eTIMS / eRITS** obligation; (c) whether the manager/platform is to
    be **appointed an MRI withholding agent** (changes rate path + remittance cadence);
    (d) the **live rates/thresholds** in force at launch (MRI 7.5%, VAT 16% / KES 5M,
    WHT 5%/20%/30%) against the current Finance Act.

## E. Data protection

11. Confirm the **controller / processor / joint-controller** mapping between
    PropManager and its landlord/manager customers per data flow (who registers for
    what; who answers DSARs). Confirm current **ODPC fees** (LN 265) and whether any
    amending Legal Notice has revised them.

---

**Provisions referenced:** Estate Agents Act Cap 533 ss.2/13/18/19; Estate Agents
(Accounts) Rules 1989; NPS Act 2011; Income Tax Act Cap 470 ss.6A/35; VAT Act 2013;
Tax Procedures (Electronic Tax Invoice) Regs 2024; KICA Cap 411A ss.83B/C/J/O/P;
Business Laws (Amendment) Act 2020; Law of Contract Act Cap 23 s3(3)/(6); Evidence
Act ss.78A/106B; Land Act 2012 ss.57/58/152; Land Registration Act 2012 s54 + LN
130/2020; Rent Restriction Act Cap 296 ss.11/15/17/29; Data Protection Act 2019 +
LN 263/264/265 of 2021.
