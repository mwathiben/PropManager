# Finance Runbook

Operator-facing reference for the Phase-81 [FINANCE-DEPTH] additions on top of an
already-mature finance surface (accounting periods, double-entry export,
late-fee automation, arrears aging, statements, gateway reconciliation).

## Deposit settlement (at move-out)

The deposit ledger (`deposit_transactions`) is now populated end-to-end:

- **On lease creation** (`LeaseController::store`): `DepositSettlementService::recordReceived`
  writes the opening `TYPE_RECEIVED` row (balance_after = deposit). Idempotent.
  `deposits:backfill-received` creates the missing row for pre-existing held deposits.
- **On move-out completion** (`MoveOutController::complete`):
  `DepositSettlementService::settle` runs inside the completion transaction —
  journals each `move_out_deduction` as a `TYPE_DEDUCTION` (linked via
  `move_out_id`), offsets `arrears_balance` as another deduction, writes the
  terminal `TYPE_PARTIAL_REFUND` / `TYPE_FULL_REFUND` (refund > 0) and flips
  `lease.deposit_status` to `refunded` / `partial_refund` / `forfeited`.
  Refund = max(0, deposit_held − deductions − arrears). Idempotent: a lease whose
  deposit is no longer `held` is skipped.

> Note: a pre-existing bug made move-out completion silently roll back — the
> `TenantActivity` insert used a non-existent `action` key, violating the
> NOT-NULL `type` column. Fixed to use `type`; the swallowing catch now logs.

## Bank reconciliation

The bank-statement flow is now wired (the import + process-queue endpoints were
"coming soon" stubs):

- **Import** (`finances.reconciliation.import`): CSV/Excel → `BankStatementImport`
  → `bank_reconciliation_queue` rows (status `pending`). Dedupes on
  (landlord_id, bank_code, transaction_reference). Bad rows + skips are returned
  to the UI. Required: `file` (csv/xlsx, ≤5MB) + `bank_code`; optional `column_mapping`.
- **Process queue** (`finances.reconciliation.process-queue`):
  `BankReconciliationService::processQueueForLandlord` matches each pending item
  (reference regex → tenant phone → amount), records a `Payment`, marks the row
  `matched`; unmatched rows become `unmatched` (retry/backoff on errors).
- The reconciliation tab also surfaces the queue (pending/unmatched/error);
  manual matching uses the existing `reconciliation.match` route.

## Accounting period close

`AccountingPeriod` close/lock already enforces no-writes-to-closed-dates
(`EnforcesAccountingPeriodLock`). Phase 81 adds a **readiness guard**:

- `PeriodCloseReadinessService::check` returns blockers for the month: **draft
  invoices** dated in the period + **pending/unmatched bank-reconciliation queue
  items** in the period.
- `finances.periods.close` refuses (validation error) when blockers exist unless
  `force=1` is passed (force is logged). The periods page shows the previous
  month's readiness before closing.
- Reopen now audits `reopened_at` / `reopened_by_user_id` / `reopen_reason`.

## Arrears aging drill-down

`FinanceFilterService::getArrearsData` now tags each overdue row with an
`aging_bucket` (`0_30` / `31_60` / `61_90` / `90_plus`) and sorts severity-first
(most days overdue at the top), so a landlord can see *which* tenants sit in the
90+ bucket — not just the aggregate.

## Late fees

Automation (`invoices:apply-late-fees` daily cron + `LateFeePolicy`) is
unchanged. Phase 81 adds:

- **Manual apply** (`finances.late-fees.apply-now`): runs the same per-invoice
  eligibility logic for the landlord's overdue invoices on demand (no waiting for
  the cron). `LateFeeService::processAllOverdueInvoices($landlordId)`.
- **Tenant projection**: `LateFeeService::previewLateFee` (already existed) is now
  surfaced on the tenant finances view as `projected_late_fee` +
  `grace_days_remaining` for each overdue invoice — a read-only warning, no write.

## Common operator tasks

| Symptom | Where to look |
| --- | --- |
| Deposit ledger empty for an old lease | Run `deposits:backfill-received`. |
| Move-out completed but deposit still 'held' | Settlement is idempotent + runs on completion; check the move-out actually reached `completed` (not rolled back — the catch now logs). |
| Bank import does nothing | It enqueues to `bank_reconciliation_queue`; run process-queue to match. Check `bank_code` + the CSV headers (reference/amount/date/description). |
| Can't close a period | Readiness blockers (draft invoices / pending reconciliation in the month). Resolve them or close with `force=1`. |
| Late fee not applied | Past grace? Under cap? Compounding frequency met? Use apply-now to run immediately. |
