# PERF-5 SoftDeletes Partial-Index Decision

## Status
**Non-adoption documented.** Phase-21 DEFER-PERF-1 (closes Phase-15 PERF-5 deferral).

## Context

Phase-15 PERF-5 was deferred with the note:
> "MySQL InnoDB lacks PG-style partial-index; revisit if `SLOW_QUERY_THRESHOLD_MS` surfaces evidence."

The candidate workaround was an `is_active` boolean shadow column on SoftDelete-using tables, maintained by an Eloquent observer, with a composite index `(landlord_id, is_active, primary_date)` to support tenant-scoped queries that filter out soft-deleted rows.

## Evidence Used

`SlowQueryServiceProvider` (Phase-15 PERF-6) has been registered since 2026-05-12 but only emits log entries when `SLOW_QUERY_THRESHOLD_MS` env is set. As of Phase-21 (2026-05-14, 2 days of audit-date time), no production deployment has surfaced sustained `deleted_at IS NULL` filtering in the top-20 slow-query log.

`php artisan slow-query:report --since=60d --top=20` (shipped in Phase-21 DEFER-OBSERV-3) is the canonical evidence-collection command. Operator workflow:

```bash
# 1. Enable instrumentation (production env)
SLOW_QUERY_THRESHOLD_MS=200

# 2. Collect 14 days of data
# 3. Run the report
php artisan slow-query:report --since=14d --top=20

# 4. If 'deleted_at IS NULL' queries appear in top 10, revisit this decision.
```

## Decision (2026-05-14)

**Do not ship the is_active shadow column workaround at this time.**

Reasons:
1. **No production evidence yet.** Phase-15 PERF-1/PERF-2/PERF-7 (Phase-15 composite indexes) plus Phase-19 INDEX-2/3/4/5/7 (Phase-19 covering indexes) already cover the hot-path tenant-scoped queries. Adding another shadow column without evidence is speculative.
2. **Shadow-column maintenance burden.** Every SoftDelete-using model gains an observer; every test that creates fixtures must keep `is_active` in sync. Phase-21 has already added the `dob` + `parental_consent_*` columns (DEFER-DPA-1) and `request_id` columns (DEFER-OBSERV-1). Adding a 3rd column family without evidence inflates the migration / fixture surface.
3. **Operator-side mitigation already exists.** `SLOW_QUERY_THRESHOLD_MS` is opt-in, but the observability stack (Phase-14 + Phase-16) gives ops a Prometheus histogram of `db_query_ms{kind=X}` — sustained drift would surface there before requiring schema change.

## Revisit Triggers

Open this decision when ANY of:
- `slow-query:report --since=14d --top=20` shows `deleted_at IS NULL` filter in top 10 by count
- p95 of `db_query_ms{kind=select}` exceeds 300ms for >7 days
- A specific operator-facing screen reports load time regression and slow-query attribution identifies SoftDeletes filtering

## Related Decisions
- Phase-15 PERF-1: `payments(landlord_id, payment_date)` composite — shipped, hot path
- Phase-15 PERF-7: `notifications(recipient_id, read_at)` composite — shipped
- Phase-19 INDEX-5: `invoices(landlord_id, status, due_date, total_due, amount_paid)` covering — shipped
- Phase-19 INDEX-9: STORED column workaround — deferred off-cycle (needs maintenance window)

## Owner
Ops team. Re-audit cadence: every 6 months OR on the trigger conditions above.
