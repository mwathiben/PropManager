# Phase-30 Integrations Runbook

Operator-facing reference for the Phase-30 INTEGRATIONS surface:
accounting export, M-Pesa B2C, Paystack webhook signature, bank
parity, accounting period locks, and the payment-plan allocator.

## Crons

| Command | Cadence (Africa/Nairobi) | What it does |
|---|---|---|
| `mpesa:reconcile-status` | every 30 min | Polls Daraja for in-flight B2C payouts (rows in `mpesa_b2c_requests.status IN (queued, sent)` older than 5 min); flips status to `succeeded`/`failed`, confirms linked DepositRefundRequest, dispatches `DepositRefundPaid`. |
| `bank-reconciliation:audit` | 05:50 daily | Emits per-bank Prometheus gauges: `bank_webhook_unmatched_count{bank=X}`, `bank_webhook_error_count{bank=X}`, `bank_webhook_silence_hours{bank=X}`. |
| `finance:close-month` | 02:30 on the 1st | Closes the previous full calendar month for every landlord (idempotent firstOrCreate). |
| `payment-plan-allocations:audit` | 05:45 daily | Walks APPROVED+COMPLETED PaymentPlans, emits `payment_plan_allocation_total_drift_count` + `payment_plan_allocation_status_drift_count` gauges. |

## Endpoints (web routes)

- `GET  /finances/accounting/export`                 — landing page with mapping diagnostics
- `GET  /finances/accounting/export/download`        — streamed IIF or Sage CSV (throttle:export)
- `GET  /finances/periods`                            — list rolling 24-month period state
- `POST /finances/periods/close`                      — manually close a month
- `POST /finances/periods/{period}/reopen`            — landlord-scoped reopen
- `POST /finance/deposit-refunds/{refund}/pay-mpesa`  — trigger B2C payout for approved refund
- `POST /webhooks/v2/paystack`                        — HMAC-SHA512 verified Paystack receiver
- `POST /api/webhooks/bank/postbank`                  — Post Bank inbound (HMAC-SHA256)
- `POST /api/webhooks/bank/familybank`                — Family Bank inbound (Bearer token)

## Tables

- `chart_of_accounts`            — per-landlord GL accounts (TenantScope, source_kind+source_key lookup)
- `mpesa_b2c_requests`           — polymorphic B2C payout ledger (status machine: queued→sent→succeeded|failed|timed_out)
- `accounting_periods`           — per-landlord closed-month registry; unique on (landlord_id, period_start)

## Key services

- `App\Services\Accounting\AccountingExportService` — streams QuickBooks IIF / Sage CSV
- `App\Services\Accounting\AccountMappingService`   — invoice/payment/expense → GL account resolver with synthetic fallback
- `App\Services\Banking\PostBankService`            — HMAC-SHA256, OAuth2 client_credentials
- `App\Services\Banking\FamilyBankService`          — Bearer token, OAuth2 client_credentials
- `App\Services\Mpesa\DepositRefundPayoutService`   — idempotent B2C payout for approved refunds
- `App\Services\Finance\PaymentAllocationService`   — applies a Payment to the active PaymentPlan installments oldest-first

## Period-lock semantics

`Invoice`, `Payment`, `Expense` use the `EnforcesAccountingPeriodLock`
trait. On every `saving` and `deleting`, if the row's effective date
(`created_at` / `payment_date` / `expense_date`) falls inside a
CLOSED `AccountingPeriod` for that landlord, `AccountingPeriodLockedException`
is thrown.

Operationally:
- A POST that would mutate a locked row returns a 500 / surfaces the
  exception (HTTP layer can map to 423 Locked).
- Post-close adjustments MUST be a credit note in the current open
  period; in-place edits to closed-period rows are intentionally not
  allowed.

## How to investigate
1. **B2C payout stuck in `sent`**: check `mpesa_b2c_requests.last_polled_at` — if `mpesa:reconcile-status` hasn't run recently, force a manual `php artisan mpesa:reconcile-status --landlord=<id> --stale-minutes=1`.
2. **Paystack webhook 401**: verify `services.paystack.secret_key` matches the Paystack dashboard "Test/Live Secret Key" exactly. Check Laravel log for "Paystack webhook rejected — signature mismatch".
3. **Bank `silence_hours` > 48**: bank's outbound queue may be wedged — open a ticket with the bank's API ops; do NOT touch the inbound endpoint.
4. **PaymentPlan status drift**: `payment-plan-allocations:audit` flagged plans where installments are fully paid but plan.status is still APPROVED. Run `php artisan payment-plan-allocations:audit` again after fixing — drift should drop to 0.
5. **Period lock blocking valid edit**: confirm the period really is closed (`finances.periods.index`). If a reopen is justified, the landlord can reopen from the UI; the audit row records who and why.
