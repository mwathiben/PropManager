# Payments Hub Rebuild — Foundation Design

Roles · Tenancy · Money flow · Onboarding context

Status: **draft for review** · Owner: product + eng · Supersedes the ad-hoc Payments Hub (PRs #72/#74/#75/#76 re-cut)

---

## 1. Why this document exists

The Payments Hub shipped as an afterthought (a blank page fixed reactively, then de-duplicated in the #72–#76 re-cut). We are rebuilding it to be world-class. The rebuild surfaced a deeper truth: the hub can't be designed without first modelling **who manages whom, on what terms, and how the money moves** — and that context belongs at **onboarding**. This doc commits that foundation on paper before any rebuild code. The hub *renders against* this model; it does not define it.

It must serve three realities at once:
- a **self-managing** landlord who collects and keeps rent;
- a **management firm or individual** who runs properties on an owner's behalf for a fee, with their own billing/payout;
- an **owner** who delegated management and wants visibility and their net funds.

…and in every one of those, **building staff (caretakers)** doing day-to-day operations.

---

## 2. Role model (target)

| Role | Means | Money | Notes |
|---|---|---|---|
| `landlord` | **Self-managing owner** — owns and runs their own properties | Collects and keeps | Narrowed from today's overloaded meaning |
| `manager` | **NEW, first-class** — a firm *or* an individual managing on an owner's behalf | Collects, takes its fee, remits the net | Capability spectrum (a firm vs a family-delegate differ — esp. withdraw rights) |
| `owner` | A property owner who **delegated** management | Receives the **net** + visibility | Already exists (Phase-102 owner-portal) |
| `caretaker` | **Building ops staff** — cleaning, maintenance, day-to-day | None | Present in *every* scenario; **not** a money/management delegate — do not conflate |
| `tenant` | Pays rent | — | Unchanged |
| `super_admin`, `water_client` | Platform / utility | — | Unchanged |

`manager` is the entity the old `landlord` role was secretly doing double duty for. We split that duty.

---

## 3. Tenancy model (the spine)

Today (`app/Traits/TenantScope.php`):
- `landlord` → scoped by `landlord_id = user.id` (the landlord **is** the scope owner).
- `caretaker` / `tenant` / `owner` / `water_client` → scoped by `landlord_id = user.landlord_id` (attached to a scope owner; fail-closed if null).
- `super_admin` → unscoped.

So `landlord_id` already means **"the managing account id."** The change is a *generalisation*, not a redesign:

- **Managing accounts** (scope owners, `landlord_id = user.id`): `{ landlord, manager }`.
- **Attached accounts** (`landlord_id = user.landlord_id`): `{ caretaker, tenant, owner, water_client }`.

> **Keep the column name `landlord_id`.** Renaming it to `managing_account_id` would touch the entire schema and every scoped query for cosmetic gain. We broaden the *semantics*, not the *name*, and document it. (A future, separate rename is possible but out of scope.)

Concretely, the only scope change is adding `manager` to the scope-owner branch:

```php
// TenantScope::bootTenantScope()  (today line ~79)
if (in_array($user->role, ['landlord', 'manager'], true)) {
    $builder->where('landlord_id', $user->id);
}
```

A `manager` ↔ `owner` link already exists: `PropertyOwner.landlord_id` = the manager, `PropertyOwner.user_id` = the owner login.

---

## 4. Migration (highest-risk — careful, evidence-based, non-breaking)

This is the one piece that touches the heart of multi-tenancy, so it gets the same discipline as the M2 god-file decompositions: characterization tests first, additive changes, full-suite + multi-role smoke verification.

**Reclassify existing accounts by evidence:**
- an existing `landlord` **with** `PropertyOwner` links (it manages for others) → becomes `manager`;
- an existing `landlord` **without** any `PropertyOwner` links (self-manages) → stays `landlord`;
- `owner` accounts already carry `role = owner` + `landlord_id` → their manager. Unchanged.
- `caretaker` accounts stay attached to their managing account. Unchanged.

**Sequence (each step shippable + tested):**
1. Accept `manager` as a role — `EnsureRole`, the `users.role` validation/enum, factories, `User::isManager()`.
2. Add `manager` to the `TenantScope` scope-owner branch, behind characterization tests that pin current landlord/caretaker/owner scoping.
3. Data migration: reclassify per the evidence rule above (idempotent, reversible).
4. Route sweep: `role:landlord` → `role:landlord,manager` wherever the capability is operational (a manager does operationally what a self-manager does). Mechanical, grep-driven.
5. Verify: full suite + the `MultiRoleRouteSmokeTest`; add `manager` to that smoke matrix.

Because a `manager` behaves like a `landlord` operationally, the split is **additive** — nothing a current landlord can do breaks; some of them simply get the correct label and the owner-facing capabilities.

---

## 5. Capability / permission model

A `PaymentsHubPolicy` exposes capability flags the controller passes and components gate on:

| Capability | landlord | manager | owner | caretaker |
|---|---|---|---|---|
| Connect / rotate gateway credentials | ✅ | ✅ (own billing) | 👁 status | 🚫 |
| Set / change payout destination | ✅ | ✅ (firm's) | ✅ (own) | 🚫 |
| Configure management fee & money-flow | n/a | ✅ | 👁 (must see terms) | 🚫 |
| Operate collections (remind, request, record) | ✅ | ✅ | 🚫 | 🚫 |
| Withdraw / trigger payout | ✅ | ⚙️ **per-relationship toggle** | own funds | 🚫 |
| Reconcile | ✅ | ✅ | 👁 | 🚫 |
| View collection health & history | ✅ | ✅ | ✅ (their portfolio) | 🚫 |
| Audit of who-did-what | ✅ | own + delegates | ✅ (oversight) | own |

The **withdraw** row is the genuine variable: a management firm typically can; an individual family-delegate typically cannot. That's stored **on the management relationship**, not the role — a manager can be trusted to withdraw for one owner and not another.

---

## 6. Money-flow model

Decided per **management relationship** (`PropertyOwner`), and the mechanic can differ **per rail**:

- **Split at source** — Paystack subaccount auto-splits the owner's net to them and the fee to the manager. Owner never waits; no float; less to reconcile. **But** the gateway levies its own charge on the split (a real, sometimes-avoidable cost), and it only works where the rail supports it (card/Paystack) — **not direct M-Pesa**.
- **Collect, then remit** — the manager's account receives everything, computes the net via `PropertyOwner.managementFeeOn()`, and disburses an `OwnerPayout`. No extra gateway charge; works on every channel incl. M-Pesa. **But** the manager holds the owner's money as float (timing & trust), and remittance is a deliberate, audited, scheduled step.

Because M-Pesa can't split, a single relationship is often **mixed** (card → split, M-Pesa → remit). The config UI therefore presents a *default mechanic* plus the *per-rail reality*, and **shows the trade-off to both the manager and the owner at config time** so neither is surprised by a Paystack charge they could've avoided or float they didn't expect.

Reuse: `PaystackSubaccountService` (splits), `PropertyOwner.managementFeeOn()` (% / flat), `OwnerPayout` + `OwnerLedgerService` (remit + balances), `LandlordPayoutAccount`, `PaymentConfiguration` + `PaymentMethodConfigService` (the credential home from PR #75).

---

## 7. Onboarding context capture

Onboarding is already role-dispatched (`OnboardingController` → per-role flows). We add, early in the property-professional flow, a **"how do you use the system?"** branch:

- **A — I manage my own properties** → `landlord`; straight to a simple get-paid setup (their methods + payout).
- **B — I manage properties for owners and charge a fee** → `manager`; capture the **fee model** (% / flat / per-owner), set the **firm's billing + payout**, choose the **default money-flow mechanic** (with the §6 disclosure), and **invite owners**.
- **C — I own properties someone else manages** → `owner`; link to the managing account, see terms, set where the net lands.

The chosen branch provisions the right `role`, `PropertyOwner` relationships, fee model, and Payments-Hub setup path — so the hub a user lands in is already shaped to their reality. Reuse `OnboardingFlow` + add the context step; don't fork the engine.

---

## 8. Reuse map — what we orchestrate, not rebuild

| Need | Existing infrastructure |
|---|---|
| Collection channels | `MpesaService` (STK), `Sms/AfricasTalkingSmsDriver`, `NotificationService` + `Notification/*` suite (bulk reminders, channel select/transport), `PushNotificationService`, `PaymentLink` |
| Fee / split / remit | `PaystackSubaccountService`, `PropertyOwner.managementFeeOn()`, `OwnerPayout`, `OwnerLedgerService` |
| Credentials & payout | `PaymentConfiguration`, `PaymentMethodConfigService` (#75), `LandlordPayoutAccount` |
| Reconciliation (system-wide) | gateway reconciliation, `Reconciliation/*`, `BankReconciliationService` |
| Onboarding | `OnboardingController` role-dispatch, `OnboardingFlow` |
| Deep ledger | Finance Hub (drill-down target; boundary set in #74–#76) |

---

## 9. Sequencing

0. **Foundation (this doc)** — model + decisions locked. *We are here.*
1. **Role/tenancy change + migration** — system foundation, characterization-tested, non-breaking.
2. **Onboarding context capture** — the entry point that provisions the right setup.
3. **The hub, rendered on the model** — cockpit → guided setup → collections (orchestrating existing channels) → payouts & owner oversight → tune & trust → coherence/polish.

Each phase: clickable mockup → review → build behind existing routes → ship → next. Each is 1–3 PRs with the re-cut's verification rigor.

---

## 10. Open questions to confirm

1. **Owner accounts** — owner self-signup (branch C) vs. manager-invites-owner only? (Affects the invitation flow + trust.)
2. **Manager cardinality** — a manager serving several managing scopes: do they need a portfolio switcher, or one account per engagement? (You said "depends" — we likely need the switcher.)
3. **Reconciliation scope for v1** — gateway + bank + owner-remittance reconciliation: all three, or staged?
4. **Fee on what** — management fee on *collected* (today's `managementFeeOn`) vs *billed*; and how late fees / deposits / water factor in.
