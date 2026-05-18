# Lease lifecycle runbook

The four lifecycle events that complement Phase 45 LEASE-COUNTER (renewal counter-proposal) — early termination, transfer/sublet, temporary pause, and auto-renewal — all governed by a shared notice-period validator.

## State machine

```
    +-----------+
    |  ACTIVE   |
    +-----------+
       |    |    |    |    |
       |    |    |    |    +----> AUTO-RENEWED (new lease created, original keeps end_date)
       |    |    |    +---------> PAUSED ---> ACTIVE (auto-resume cron) | CANCELLED (manual)
       |    |    +--------------> TRANSFERRED (Lease.tenant_id swapped)
       |    +-------------------> TERMINATED (Lease.is_active=false, Lease.end_date=termination_date)
       +------------------------> EXPIRED (end_date passes, no auto-renew, no counter)
```

## Surface ownership

| Subject | Path | Lineage |
|---|---|---|
| `Lease.is_active` + `end_date` | core schema | Phase 1 baseline |
| Counter-proposal during renewal | `lease_renewal_counter_history` table | Phase 45 LEASE-COUNTER |
| `LeaseTermination` (early termination) | `lease_terminations` table | **Phase 61 TERMINATION** |
| `LeaseTransfer` (assignment / sublet) | `lease_transfers` table | **Phase 61 TRANSFER** |
| `LeasePause` (rent vacation / hardship) | `lease_pauses` table | **Phase 61 PAUSE** |
| Auto-renewal on expiry | `leases.auto_renew` + `renewed_from_lease_id` | **Phase 61 RENEWAL-AUTO** |
| Notice-period validation | `config/lease.php` + `NoticePeriodValidator` | **Phase 61 NOTICE-LIFECYCLE** |

## Phase 61 LEASE-LIFECYCLE (2026-05-18)

### Notice periods

`config/lease.php notice_periods` (days):

| Action | Default | Env override |
|---|---|---|
| `termination` | 30 | `LEASE_NOTICE_TERMINATION_DAYS` |
| `transfer` | 14 | `LEASE_NOTICE_TRANSFER_DAYS` |
| `pause` | 7 | `LEASE_NOTICE_PAUSE_DAYS` |

30 days for termination is the Kenya Landlord and Tenant Act baseline. `NoticePeriodValidator::validate(action, effectiveDate, ?landlordOverrideDays)` throws `ShortNoticeException` with a translation-keyed reason when the effective date is closer than the threshold.

### Termination

State machine: `pending → acknowledged → completed` (or `disputed` / `withdrawn`).

```php
POST /leases/{lease}/terminate
{
  "termination_reason": "mutual",
  "termination_date": "2026-06-30",
  "reason_text": "agreed early end"
}
```

Either party (landlord or tenant on the lease) may initiate. The other party acknowledges or disputes. `LeaseTerminationService::complete` is what actually flips `Lease.is_active=false` + sets `Lease.end_date=termination_date` — until then the lease keeps invoicing.

### Transfer / sublet

State machine: `requested → landlord_approved → completed` (or `rejected` / `withdrawn`).

```php
POST /leases/{lease}/transfer
{
  "incoming_tenant_email": "new@example.test",
  "transfer_date": "2026-06-15",
  "transfer_fee_amount": "500.00",
  "reason_text": "relocating"
}
```

Three actors: outgoing tenant (initiates), incoming tenant (nominated), landlord (approves). `LeaseTransferService::complete` swaps `Lease.tenant_id` from outgoing to incoming. Landlord approval surface is intentionally landlord-side; Phase 61 ships the request and approve/reject/complete service methods plus the outgoing-tenant request route — the landlord queue UI is deferred to a follow-up cycle.

### Pause (rent vacation)

State machine: `active → completed` (cron auto-resume) or `cancelled` (manual).

```php
POST /leases/{lease}/pause
{
  "pause_start": "2026-06-01",
  "pause_end": "2026-08-31",
  "reason": "tenant_hardship"
}
```

Landlord-only. `LeasePauseService::start` flips `Lease.is_active=false` for the pause window. `lease-pause:auto-resume` cron daily 06:00 Africa/Nairobi finds pauses past `pause_end` and flips `is_active=true` + sets `auto_resumed=true`. Emits `lease_pause_resumed_count` gauge.

The lookup uses `lockForUpdate` inside `DB::transaction` for concurrent-cron safety (the Phase-60 pattern).

### Auto-renew

`leases.auto_renew` defaults to true. `lease:auto-renew` cron daily 07:00 Africa/Nairobi scans leases where `auto_renew=true AND is_active=true AND end_date BETWEEN now AND now+30d`, creates a new Lease row for each via `LeaseRenewalAutoService::renew`:

- `start_date = old.end_date + 1 day`
- `end_date = start_date + (old term length in days)`
- Same financial terms (rent, deposit, service_charge)
- `renewed_from_lease_id = old.id` for the audit chain

Per-lease opt-out via `PATCH /leases/{lease}/auto-renew` with `auto_renew=false`. Cron runs after Phase-45 `lease-renewal:expire-stale-counters` (03:00) so counter-proposals resolve first.

`--dry-run` flag logs candidates without creating new leases; emits `lease_auto_renewed_count` gauge.

### Cron ordering

| Cron | Time | Phase | Purpose |
|---|---|---|---|
| `lease-renewal:expire-stale-counters` | 03:00 | 45 | Resolve counter-proposals first |
| `lease-pause:auto-resume` | 06:00 | 61 | Resume elapsed pauses |
| `lease:auto-renew` | 07:00 | 61 | Auto-create next-period leases |

## Cross-references

- Phase 17 MONEY — rent reminder + Lease.is_active gating (a paused lease bypasses reminders)
- Phase 29 WORKFLOW — `RentReminderPolicy` (Phase-29 WF-RENT-REMIND uses Lease.reminder_tier)
- Phase 45 LEASE-COUNTER — counter-proposal during renewal (this runbook is the complement)
- **Phase 61 LEASE-LIFECYCLE** — termination + transfer + pause + auto-renew
