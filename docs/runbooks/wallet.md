# Wallet Runbook

Phase-76 WALLET-DEEP. The tenant wallet is a per-lease credit pool that holds
tenant overpayments and landlord-issued credit, applied to invoices manually,
automatically, or by the tenant.

## Money convention (read first)

The wallet / credit-note / statement subsystem uses **decimal-float money**
(`decimal(10,2)` columns, PHP `float` in business logic). This predates the
cents-integer convention (`*_cents`) used by Phase 30+ payment code. **Match the
local decimal convention when touching this subsystem** — do not introduce cents
here.

## Balance model (per currency)

- The landlord's **default currency** balance lives in the legacy
  `leases.wallet_balance` scalar — the source of truth for that currency, so the
  ~10 existing readers/writers (webhooks, payment processors, InvoiceService)
  keep working unchanged.
- **Non-default currencies** live in `lease_wallet_balances` (unique
  `lease_id, currency`). A KES-default landlord with a USD overpayment gets a
  `USD` row; their KES credit stays in the scalar.
- `App\Services\Wallet\WalletService` is the single boundary:
  - `credit(lease, amount, reason?, paymentId?, currency?, creditNoteId?)`
  - `apply(lease, amount, reason?, invoiceId?, currency?): float` (caps at balance)
  - `applyToInvoice(invoice, amount?): float` (deduct + bump invoice
    amount_paid/wallet_applied/status, in the invoice's currency)
  - `balanceFor(lease, currency?)`, `balancesFor(lease)`, `ledger(lease, currency?)`
  - The **default-currency path delegates to `Lease::creditToWallet/
    deductFromWallet`** so the transaction + `lockForUpdate` + `afterCommit`
    invariant stays in ONE place; the non-default path locks the balance row.
  - **All mutations require an outer `DB::transaction`** (guarded by
    `throw_unless(DB::transactionLevel() > 0, ...)`).

## Credit notes → wallet

`CreditNoteService::applyToWallet(creditNote)` moves an approved note's remaining
balance into the tenant wallet (in the note's invoice currency, else the landlord
default), linking the wallet transaction via `wallet_transactions.credit_note_id`.
Idempotent (the `canBeApplied()` guard makes a re-call a no-op). A note is applied
either to an invoice (`applyToInvoice`) OR to the wallet (`applyToWallet`), never
both. `CreditNotePolicy` gates every credit-note action to the owning landlord.

## Auto-apply (per landlord)

`landlord_wallet_settings.auto_apply_mode` (resolved + cached by
`WalletAutoApplyResolver`, default `config('wallet.default_auto_apply_mode')`):

- `off` — never auto-apply (manual / tenant self-apply only).
- `on_invoice_create` — `InvoiceService` deducts wallet credit when an invoice is
  generated (currency-matched via `WalletService::apply`).
- `oldest_first_sweep` — skip at create; the **`wallet:auto-apply` cron** (daily
  05:15) applies each lease's standing credit to its oldest unpaid same-currency
  invoices. Idempotent; cross-currency invoices skipped.

Landlords set the mode at `/wallet/settings`.

## Tenant self-apply

`/tenant/wallet` (tenant-only) shows per-currency balances + ledger + outstanding
invoices. `POST /tenant/wallet/apply` (throttled) applies credit to an invoice
**gated on `invoice.lease_id === the tenant's own active lease`** (not merely
landlord scope — a tenant sees their landlord's invoices under TenantScope, so the
explicit lease check is load-bearing).

## Same-currency guard

There is **no FX engine**. `WalletService::apply` always uses the obligation's own
currency, so a USD balance can never settle a KES invoice; an explicit
cross-currency request throws `CurrencyMismatchException` (422). A tenant with only
USD credit applying to a KES invoice draws 0 and gets a wallet error.

## Statement integration

`StatementService::forTenant` running balance = **charges − payments − credit-note
applications**. Credit notes reduce the obligation and are NOT Payment rows, so
they are subtracted explicitly (opening via `creditNoteTotalBefore`, in-window as
`credit_note` rows). **Wallet movements are INFORMATIONAL rows only** (charge =
payment = 0): a wallet credit is either an already-counted payment overpayment or
an already-counted credit note — counting the wallet leg too would double-count.
The statement also shows a per-currency wallet-balance summary.

## Gauges (wallet:rollup, daily 05:25, top-50 landlords)

- `wallet_total_credit_balance{landlord_id, currency}` — standing credit float.
- `credit_notes_pending_count{landlord_id}` — credit-note approval backlog.
- `wallet_applied_24h_count{landlord_id}` — wallet debits in the last 24h.

## Incident playbook

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| `credit_notes_pending_count` spikes | Landlord not approving notes | Nudge landlord; pending notes never reach a tenant until approved + applied |
| `wallet_total_credit_balance` grows unbounded | Auto-apply `off` or no invoices to absorb credit | Confirm the landlord's `auto_apply_mode`; consider `oldest_first_sweep` |
| Tenant reports credit not applied | Cross-currency (USD credit, KES invoice) | Expected — same-currency only; advise a same-currency invoice or a manual landlord adjustment |
| Statement balance disagrees with tenant's mental model | Wallet rows are informational | The account balance is charges − payments − credits; wallet credit shows separately as available credit |

## Cross-references

- `docs/runbooks/finance.md` — payments, allocation, reconciliation.
- Phase 30 PAY-ALLOC (PaymentPlan allocation), Phase 42 PAYMENTS-INTL (currency).

## CI gates

- `Phase76WalletCoreTest`, `Phase76CreditWalletTest`, `Phase76AutoApplyTest`,
  `Phase76TenantApplyTest`, `Phase76StatementWalletTest`
- `Phase76WalletDeepSurfaceTest` — cross-category presence map.
