# Legal hold — operator runbook

Owner: Compliance / Legal workstream.
Last touched: Phase 65 — LEGAL-HOLD-EXPAND.

A legal hold is a court-ordered or counsel-initiated preservation
directive that overrides the platform's normal retention sweeps.
Held records survive the daily retention crons until the hold is
explicitly released.

Phase 64 shipped the polymorphic foundation (MessageThread-only).
Phase 65 expanded coverage to Document + Invoice + Ticket, added a
landlord-facing UI for create/release, bulk hold for litigation, a
tenant-litigation preset, retention-cron integration, and a
regulator-ready CSV audit export.

---

## 1. When to use legal hold

Place records under hold when ANY of the following applies:

1. **Court order received** — civil litigation, criminal investigation,
   regulatory inquiry. The order names specific records to preserve.
2. **Litigation reasonably anticipated** — pre-litigation hold while
   counsel evaluates a dispute (Kenya CPC + common-law spoliation
   doctrine).
3. **DPA Section 39 / GDPR Article 21 right-to-object pending review**
   — preserve disputed processing records during the review window.
4. **Internal investigation** — fraud, misconduct, dispute resolution.

Lawful basis: Kenya DPA Section 30 / GDPR Article 6(1)(c) — legal
obligation processing. Articulated as the LegalHold model's
`getLawfulBasis() => 'legal_obligation'`. Article 17(3)(b) exempts
legal-obligation processing from right-to-erasure requests; held data
will appear in the DataExportService's `legal_holds_blocking_erasure`
stanza so requesters see what survived erasure and why.

Don't use legal hold for: ordinary business archival (use the
existing per-subject retention policies); routine dispute handling
where no preservation directive is on file.

---

## 2. Single-subject hold via UI

1. Navigate to the relevant subject's Show page (Invoice, Ticket,
   Document, or Message Thread).
2. Click "Place under legal hold" → slide-over modal opens.
3. Enter a reason (10-500 chars). Recommended format: court ref +
   short description.
   Example: `Court order CV/2026/0123 — preservation directive: invoice
   trail for tenant Smith`.
4. Click "Place hold". Modal closes; success flash confirms.
5. Held subject is now excluded from retention sweeps.

The hold appears in the sidebar `Legal holds` page with a rose badge
showing the active count for the landlord.

---

## 3. Bulk hold for litigation requests

A real litigation request often names dozens of records ("all 2025
invoices for tenants Smith / Jones / Patel"). Manual one-by-one
holds are unworkable.

Bulk endpoint: `POST /legal-holds/bulk` accepts:

```json
{
  "subject_type": "App\\Models\\Invoice",
  "subject_ids": [1234, 1235, 1236, ...],
  "reason": "Court order CV/2026/0123 — preservation order"
}
```

- Wraps a single `DB::transaction` minting N rows.
- Validates every id belongs to the acting landlord BEFORE writes
  (whole batch rejected on any cross-tenant leak).
- Busts the `legal_hold:ids:<modelClass>` cache once at end (not N
  times).
- Capped at `config('legal_hold.bulk_max', 100)` with a hardcoded
  500 ceiling.
- Symmetric `DELETE /legal-holds/bulk` releases.

Rate-limited via `throttle:legal-hold` (10/min/user — tighter than
single-subject because attacker leverage is higher).

---

## 4. Tenant-litigation preset

One-click hold every record tied to a specific tenant — invoices,
tickets, documents, message threads — atomically.

Trigger: `POST /tenants/{tenant}/legal-hold` with `{reason}`. UI
exposes "Legal hold all related records" button on the Tenants/Show
page beside the Phase 64 Message CTA.

Orchestrates 5 inner BulkHoldService calls inside ONE outer
`DB::transaction` at the controller layer. On any mid-flight inner
failure the outer transaction rolls back ALL subject_types — partial
hold state never observable.

Emits `tenant_litigation_hold_subjects_count{subject_type}` counter
so ops sees per-subject distribution.

---

## 5. Release procedure

UI: `/legal-holds` Active tab → click Release on the row. Confirm
modal → released_at + released_by stamped on the LegalHold row (NOT
row-deleted — audit trail preserved).

The row moves to the Released tab on next page load.

Bulk release: `DELETE /legal-holds/bulk` with the same payload shape
as bulk hold.

Released-at + released-by are captured for the regulator audit
export.

---

## 6. Audit-export procedure (regulator inspection)

1. Navigate to `/legal-holds/audit-export`.
2. Pick a date range (≤ 2 years; enforced by the controller).
3. Click "Download CSV".

CSV columns: `event_at, event_type, subject_type, subject_id, reason,
actor_user_id, actor_user_name, lawful_basis`. UTF-8 BOM + LF line
endings + Excel-compatible. CSV-injection prefixes (`=`, `+`, `-`,
`@`) on the reason field are neutralised with a leading apostrophe.

Streamed via Phase 59 SIGNED-URLS pattern — 5-minute signed URL,
browser-direct download, doesn't tie up PHP-FPM.

CSV file persists under `exports/{landlord_id}/legal-hold-audit/<random>.csv`
on the tenant disk and inherits the Phase 59 `export_zip` 7-day
retention sweep (no operator cleanup needed).

---

## 7. Artisan emergency recipes

Operator-mode override when UI isn't reachable:

```sh
# Place a single hold from CLI
php artisan tinker --execute="\\App\\Support\\LegalHoldRegistry::hold(
  \\App\\Models\\MessageThread::find(42),
  \\App\\Models\\User::find(7),
  'Court order CV/2026/0123 — emergency operator hold'
);"

# Release
php artisan tinker --execute="\\App\\Support\\LegalHoldRegistry::release(
  \\App\\Models\\MessageThread::find(42),
  \\App\\Models\\User::find(7)
);"

# Aggregator gauge re-emit (normally cron at 04:45 EAT)
php artisan legal-hold:audit-exclusions
```

---

## 8. Cron interaction matrix

| Cron | Cadence | Honors holds? | Gauge emitted |
|------|---------|---------------|---------------|
| `messages:enforce-retention` | daily 03:15 EAT | Yes (Phase 64) | `messages_legal_hold_count` |
| `storage:enforce-retention` | daily 02:30 EAT | Yes (Phase 65) for `kyc_doc` / `lease_doc` / `invoice_pdf` Document branches | `files_retention_held_count{subject}` |
| `legal-hold:audit-exclusions` | daily 04:45 EAT | (aggregator) | `retention_legal_hold_exclusions_count{subject_type}` |
| `legal-hold:sweep-stale` | daily 05:10 EAT | (nudges, never deletes) | `legal_hold_stale_count` |

Schedule order is intentional: retention crons run first; aggregator
runs after so the emitted exclusion count reflects post-purge truth.

DataExportService (right-to-erasure / GDPR Article 20 exports) is
synchronous on request; the `legal_holds_blocking_erasure` stanza
always reflects current state. **Phase-68 HOLD-GUARD**: DataDeletionService
(GDPR Art. 17 erasure) is now hold-aware — held documents are preserved
(Art. 17(3)(b) carve-out), the rest is erased, and the request completes;
each preserved doc increments `legal_holds_blocking_erasure`.

---

## Deletion guard (Phase-68 HOLD-GUARD)

A subject under an active hold cannot be deleted on **any** path — the
`HasLegalHolds` deleting observer throws `LegalHoldActiveException` for
Document / Invoice / Ticket / MessageThread (manual delete, soft-delete,
cascade). The attempt renders a friendly error and increments
`legal_hold_blocked_deletions_count{subject_type}`. To delete a held
record, release the hold first. This is belt-and-suspenders on top of the
retention-cron exclusions in the matrix above.

---

## Stale holds

A hold active longer than `config('legal_hold.stale_after_days')` (default
365) is **stale** — litigation has probably resolved but the hold still
blocks retention. `legal-hold:sweep-stale` (daily 05:10 EAT):

1. Emits `legal_hold_stale_count` (platform-wide count of stale active holds).
2. Fires/resolves the `legal_hold_stale` alert (sev3, email).
3. Emails each owning landlord a `StaleHoldReminderMailable` listing their
   stale holds with a deep link to `legal-holds.index?status=active`, at
   most once per `config('legal_hold.stale_reminder_cooldown_days')`
   (default 30) — tracked via `legal_holds.last_reminded_at`.

The sweeper **never deletes** anything; it only nudges. On-call action when
`legal_hold_stale` fires: confirm with the landlord whether each hold is
still required; release the resolved ones (UI or the CLI recipe above).
Knobs: `LEGAL_HOLD_STALE_AFTER_DAYS`, `LEGAL_HOLD_STALE_REMINDER_COOLDOWN_DAYS`.

**Orphaned holds** (`legal_hold_stale_orphan_count` > 0): the hold's subject
row no longer exists (hard-deleted out from under the hold), so it can be
neither reminded nor released through the UI and would otherwise pin
`legal_hold_stale` open forever. Each is logged as `legal_hold_stale_orphan`
with the hold id + subject — release them directly: `LegalHold::find(<id>)->update(['released_at' => now()])`.

The soft-delete purge cron (`soft-deleted:purge`) excludes held rows from its
batch (Phase-68 HOLD-GUARD), so a held + soft-deleted Document/Invoice is
preserved and the purge loop still terminates.

---

## Per-subject history (Phase-68 HISTORY)

The landlord-wide audit export (section 6) answers "all hold actions in a
window." For the full chain of custody of **one** subject, use the per-subject
timeline:

- UI: the "View hold history" / clock affordance on `/legal-holds`,
  Documents, Invoices/Show, Tickets/Show → `LegalHolds/History.vue`.
- Route: `GET /legal-holds/history?subject_type=&subject_id=`
  (`legal-holds.history`), gated by `LegalHoldPolicy::viewHistory` → a
  cross-tenant subject is rejected, never returned.
- CSV: `GET /legal-holds/history/export?...` (`legal-holds.history.export`)
  streams a one-subject chain-of-custody CSV (BOM + injection guard) via the
  Phase-59 signed-URL pattern, nested under `exports/{actor}/legal-hold-history/`.

## Bulk-hold UI (Phase-68 BULK-UI)

Litigation that touches many documents at once uses the multi-select grid on
`/documents` (landlord/super-admin): per-row + select-all checkboxes feed a
sticky action bar.

- All-unheld selection → "Place hold on N" opens `BulkHoldModal` →
  `POST /legal-holds/bulk` (`legal-holds.bulk.store`).
- All-held selection → "Release N" → `DELETE /legal-holds/bulk`
  (`legal-holds.bulk.destroy`).
- Mixed selections show a hint (no action); the client caps at
  `config('legal_hold.bulk_max')` and the server re-enforces it.

`BulkHoldService::holdAll` is idempotent — it skips subjects already under an
active hold (MySQL treats `released_at = NULL` as distinct in the unique
index, so without this a re-submit would mint a duplicate active hold). The
single-subject `LegalHoldRegistry::hold()` is idempotent for the same reason.

> Routing note: `DELETE /legal-holds/{legalHold}` is `whereNumber`-constrained
> so `DELETE /legal-holds/bulk` resolves to the bulk controller (it was
> shadowed → 404 before Phase 68).

---

## 9. Cross-references

- [inbox.md](inbox.md) §6 — Phase 63 messages:enforce-retention + Phase 64 legal-hold integration.
- [storage.md](storage.md) — Phase 59 retention policies + signed-URL streaming.
- [alert-thresholds.md](alert-thresholds.md) — Phase 65 gauges: `legal_holds_active_count` (sev4 silent-compliance-failure), `tenant_litigation_hold_subjects_count`, `retention_legal_hold_exclusions_count`, `files_retention_held_count`, `files_retention_orphan_count` (sev4 > 5/24h).
- [frontend-polish.md](frontend-polish.md) — wizard styling convention used by LegalHolds/Index.vue.

Adding a new holdable subject:
1. Add the model class to `LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES`.
2. Add `use HasLegalHolds;` (and `use Auditable;` if not already
   present) to the model.
3. If the subject has a per-subject retention cron, port the
   `LegalHoldRegistry::heldIdsFor($modelClass)` exclusion pattern
   from Phase 64 messages:enforce-retention.
4. Update this runbook + alert-thresholds.md.
