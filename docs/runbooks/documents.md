# Documents Runbook

Operator-facing reference for the Phase-82 [DOCUMENTS-DEPTH] additions: document
lifecycle (expiry tracking, reminders, renewal/supersede) and notice generation,
on top of the existing Archive hub + retention (Phase 59) + legal holds (Phase 64/65).

## Lifecycle fields

`documents` carries: `issue_date`, `expires_at`, `reminder_days` (per-document
reminder window; falls back to 30), `is_renewable` (only renewable docs are
reminded), and `superseded_by_document_id` (the renewal chain). The upload form
captures issue/expiry/renewable/reminder; the type taxonomy now includes
insurance, compliance_cert, title_deed, inspection_report, notice.

Key Document scopes/helpers:
- `current()` — not superseded (the renewal chain's old versions drop out).
- `expiringSoon($days)` — has expiry within the window.
- `dueForReminder($default=30)` — renewable + current + within its own
  (`reminder_days` ?? default) window. Drives the reminder cron + landlord surface.
- `expiryStatus()` — expired | expiring_soon | valid | none (per-doc window).

## Landlord visibility

- **Archive → Documents tab** shows the expiry column + status chip and an expiry
  filter (all / expiring / expired); superseded versions are hidden by default.
  (This also fixed pre-existing column bugs — the tab filtered on non-existent
  `name`/`type`/`original_name` columns and would 500 on use.)
- **Landlord dashboard** has an "Expiring Documents" action card (renewable docs
  within 30 days) linking to the Archive tab filtered `?expiry=expiring`.
- **Gauge**: `documents:expiry-rollup` (weekly Sun 05:15 Africa/Nairobi) emits
  `landlord_documents_expiring_30d{landlord_id}` — visibility-only, no alert.

## Expiry reminders (the active loop)

`documents:scan-expiring` (daily 07:35 Africa/Nairobi) fires
`DocumentExpiryApproaching` for each renewable, current document in its reminder
window — once per document per calendar month (cache-idempotent, like
`leases:scan-renewals`). `NotifyOnDocumentExpiry` (queued + backoff) notifies the
landlord (and the tenant when the document belongs to their lease/KYC) via
`NotificationService` (respects NotificationPreference). Notification type
`document_expiry` (URGENCY_IMPORTANT). Non-renewable / superseded / no-expiry docs
are skipped. Before Phase 82 the tenant banner was the only expiry surface and
nobody got a notification.

## Renewal / supersede

`documents.renew` (POST, role:landlord,caretaker) uploads a fresh version with a
new expiry, creates a new Document carrying the type/title/reminder_days, and sets
the old document's `superseded_by_document_id`. The old row is kept (audit +
retention) but leaves the expiring surface. Hold-aware: a held document can still
be superseded (it just can't be deleted).

## Notice generation

`documents.generate-notice` (POST `/leases/{lease}/generate-notice`,
role:landlord,caretaker) → `DocumentGenerationService::generateNotice` renders
`resources/views/documents/notice.blade.php` (type: rent_increase | arrears |
general) to PDF via dompdf, stores it on the tenant disk, and creates a Document
(documentable = Lease, type = `notice`). Generated notices then live in the same
archive / retention / legal-hold pipeline as uploaded documents (previously
notices were emailed text only).

## Retention + legal-hold interplay (unchanged, Phase 59 / 64-65)

`storage:enforce-retention` (daily 02:30) purges documents past their per-subject
window (lease_doc 7yr, kyc_doc 5yr), **excluding** legally-held documents
(`LegalHoldRegistry::heldIdsFor(Document::class)`). A held document cannot be
deleted (the `HasLegalHolds` deleting guard) but can be superseded by a renewal.

## Common operator tasks

| Symptom | Where to look |
| --- | --- |
| Document not reminding | Must be `is_renewable` + `current` + within its window; check `dueForReminder`. |
| Reminder fired twice | Not possible same month — cache-idempotent per (document, year-month). |
| Expired doc still in the expiring list | If superseded it drops out; otherwise it shows as `expired`. Renew it. |
| Archive type/search filter 500 (historic) | Fixed in Phase 82 (real columns title/file_name/document_type). |
| Notice not generated | Check the lease belongs to the landlord + dompdf render; the Document is created with type `notice`. |
