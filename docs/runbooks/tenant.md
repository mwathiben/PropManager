# Tenant Portal Runbook

Phase-28 [TENANT-PORTAL] shipped the MVP. Phase-45 [TENANT-DEPTH] deepens it. This runbook covers the day-2 operational surface for the tenant-facing flows: what each cron does, where the data lives, how to verify a feature is working, and what to do when something breaks.

## Statement export (Phase-45 STATEMENT-DEPTH)

The tenant statement endpoint at `GET /tenant/statement` accepts a `?period=` query and renders the resulting ledger via Inertia. The xlsx export at `GET /tenant/statement.xlsx` builds a single-period detail sheet plus (for multi-month windows) a `Monthly Summary` sheet with charges, payments, net, and the closing balance per month.

### Period modes

| Period | Window | Notes |
|--------|--------|-------|
| `current_month` (default) | start-of-month to end-of-month | Single sheet |
| `last_month` | previous calendar month | Single sheet |
| `last_3_months` | last 3 calendar months | 2 sheets — detail + Monthly Summary |
| `year_to_date` | Jan 1 of current year to today | 2 sheets |
| `calendar_year` | full Jan 1 to Dec 31 | 2 sheets |
| `last_12_months` | rolling 12-month window | 2 sheets |
| `custom` | `?from=YYYY-MM-DD&to=YYYY-MM-DD` | Clamped to ≤ today + ≥ today-5yr; from must be ≤ to (else snapped to to.startOfMonth) |

### Filters

`?types[]=charge`, `?types[]=payment`, `?min_amount=`, `?max_amount=`. The running balance still walks every event under the hood — only the row emission is suppressed — so opening/closing balances stay invariant under filter changes.

### Column preferences

`PATCH /tenant/statement/preferences` accepts `{columns: ['date', 'description', ...]}` from the catalog in `TenantStatementPreference::ALLOWED_COLUMNS`. The row is keyed UNIQUE on `user_id`. Columns missing from the row fall back to `DEFAULT_COLUMNS`; an empty / all-bogus list also falls back.

## Ticket photo annotations (Phase-45 TICKET-PHOTOS)

Tenants and landlords can annotate maintenance-ticket photo attachments with pen / rect / arrow / text overlays. The annotated copy is persisted as a sibling `Document` row pointing back to the original via `annotates_document_id`; `annotation_data` stores the canvas scene JSON for re-edit.

### Endpoint

`POST /tickets/{ticket}/attachments/{document}/annotation` accepts `{image: <data-url or base64 PNG>, annotation_data: [...]}`. Authorisation: super_admin all; landlord/caretaker by `landlord_id` match; tenant by `ticket.tenant_id` match. Refuses non-image attachments + already-annotated documents (cannot annotate an annotation).

### Re-edit flow

Pages/Tickets/Show.vue groups attachments by `annotates_document_id`. The "Re-edit annotation" button hydrates `TicketPhotoAnnotator.vue` from `annotation_data` so a user can fix or extend an annotation without restarting from scratch.

### Storage

Annotated PNGs land at `tickets/{ticket_id}/annotation-{Ymd-His}-{rand4hex}.png` on the local disk. Retention follows the parent ticket — cascading delete on `documents.documentable` via the existing morphMany.

## Lease renewal counter-offers (Phase-45 LEASE-COUNTER)

Phase 29 shipped accept / reject. Phase 45 adds a counter cycle: tenant submits alternative `counter_rent_amount_cents` + `counter_end_date` + optional message; landlord accepts (counter values promote to canonical `proposed_*` + status → accepted), rejects (status → rejected), or re-proposes (new `proposed_*` + status → proposed, fresh tenant cycle).

### Endpoints

| Method + path | Actor | Action |
|---------------|-------|--------|
| `POST /tenant/renewals/{renewal}/counter` | tenant | Submit counter (only from STATUS_PROPOSED) |
| `POST /landlords/renewals/{renewal}/counter/accept` | landlord | Promote counter to proposed, status → accepted |
| `POST /landlords/renewals/{renewal}/counter/reject` | landlord | status → rejected with optional reason |
| `POST /landlords/renewals/{renewal}/counter/re-propose` | landlord | New proposed_* values, status → proposed, fresh tenant cycle |

All three landlord endpoints require `role:landlord,caretaker`; super_admin always passes; the renewal's `landlord_id` must match `user.id` OR `user.landlord_id`. 422 when the renewal isn't currently `counter_proposed`.

### Audit history

Every status transition writes a `lease_renewal_counter_history` row capturing rent / end_date / message at the moment of action. Walk via `LeaseRenewal::history()` ordered ascending by `created_at` for a full negotiation timeline.

### Expiry cron

`lease-renewal:expire-stale-counters` daily 06:00 Africa/Nairobi. Any `counter_proposed` row older than `LeaseRenewal::COUNTER_EXPIRY_DAYS` (14 days) flips to `expired` + writes an `ACTION_EXPIRED` history row + emits `lease_renewal_counter_expired_count{landlord_id}` gauge. Alert: sev4 at 5/day per `alert-thresholds.md`.

## Payment plan modifications (Phase-45 PAY-PLAN-MOD)

Phase 28 shipped one-shot payment plans. Phase 45 lets a tenant propose a new installment schedule after approval; landlord re-approves or rejects.

### Endpoints

| Method + path | Actor | Action |
|---------------|-------|--------|
| `POST /tenant/payment-plans/{plan}/modifications` | tenant | Propose new installments (plan → modified_pending) |
| `POST /finance/payment-plan-modifications/{modification}/approve` | landlord | DELETE unpaid + INSERT proposed; plan → approved |
| `POST /finance/payment-plan-modifications/{modification}/reject` | landlord | plan → approved (revert), modification → rejected |

### Safeguards

`PaymentPlanModificationService::propose` rejects any of:
- Plan is not currently `STATUS_APPROVED` (cannot modify a not-yet-approved or already-modified-pending plan)
- Fewer than 2 proposed installments (single-installment is a deferred lump sum, not a modification)
- `sum(proposed.amount_cents) !== sum(unpaid_originals.amount_cents)` (modification cannot reduce outstanding balance)
- Paid installments are immutable — only the `STATUS_PENDING` rows are considered

### Stale-modification cron

`payment-plans:audit-stale-modifications` daily 06:15 Africa/Nairobi emits `payment_plan_modification_pending_24h{plan_id}` gauge counting days-since-creation for pending modifications >24h. Alert: sev3 at 7-day threshold.

## Emergency contact verification (Phase-45 EMERGENCY-CONTACT-SMS)

### Source-of-truth rule

`emergency_contacts` is canonical. `users.emergency_contact_name` + `users.emergency_contact_phone` are mirrors maintained automatically by `EmergencyContact::booted()`:
- On `saving` an `is_primary=true` row: clear `is_primary` on every other row for the same tenant (single-primary invariant)
- On `saved` an `is_primary=true` row: write `{name, phone}` into the user record

Phase-45 backfill migration `2026_05_17_153300_phase45_emergency_contact_sms_add_columns` populated `users.emergency_contact_*` from the canonical row for every existing tenant. Code reading from `users.emergency_contact_*` keeps working — it just reflects the canonical row now.

### SMS provider swap

`config/sms.php` reads `SMS_DRIVER`:
- `stub` (default): logs to laravel.log + records to `StubSmsDriver::$sent` for assertions; NEVER hits the network. CI + dev stay on this.
- `africastalking`: Africa's Talking adapter — set `AFRICASTALKING_USERNAME` + `AFRICASTALKING_API_KEY` + optional `AFRICASTALKING_SENDER_ID`.

To add a new provider, implement `App\Services\Sms\Contracts\SmsDriver::send(phone, message): string` and register a branch in `AppServiceProvider::register()`'s `SmsDriver::class` factory.

### Tenant flow

| Method + path | Action |
|---------------|--------|
| `POST /tenant/emergency-contacts/{contact}/send-otp` | Generate + cache 6-digit code at `otp:contact:{id}` for 10 minutes; dispatch SMS via configured driver. Rate-limited: max 3 sends per contact per 24h via `verification_attempts_24h`; counter resets after 24h since `last_otp_sent_at`. |
| `POST /tenant/emergency-contacts/{contact}/verify-otp` | Validate `code` (6 digits), hash_equals against cache, consume entry, write `verified_at = now()`. |

Both endpoints are `throttle:sensitive` + ownership-guarded (`tenant_id === user.id`).

### Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Tenant claims they never got the SMS | Stub driver in prod (mis-configuration) | Check `config('sms.driver')` — should be `africastalking` in prod |
| Africa's Talking returns 4xx | Stale credentials or insufficient balance | Check the Africa's Talking dashboard; rotate API key |
| Verified_at not setting after correct code | Cache backend not shared across workers | Confirm `CACHE_STORE` is redis/database (not array) in prod |
| Mirror columns stale | Listener bypassed (e.g. raw DB::table update) | Re-run the backfill block from the Phase-45 migration; or invoke `EmergencyContact::find($id)->touch()` to retrigger saved hook |

## Cross-references

- `docs/runbooks/alert-thresholds.md` — sev3/sev4 rows for `lease_renewal_counter_expired_count` + `payment_plan_modification_pending_24h`
- Phase-28 [TENANT-PORTAL] PRD — original MVP scope
- Phase-45 [TENANT-DEPTH] PRD — `phase-45-audit-prd.json` for the full finding list + audit_closeout
