# Workflow Automation (Phase 29)

Runbook for the landlord-driven workflow automation surface
introduced in Phase 29 [WORKFLOW-AUTOMATION]. Mirrors the shape of
`docs/runbooks/pwa.md`, `docs/runbooks/bi.md`, and
`docs/runbooks/tenant-portal.md`.

## Overview

Phase 29 closes five automation gaps and absorbs the Phase-28
deferred landlord-approval UIs:

- Tiered rent reminder cadence (one-size-fits-all → per-tenant tier)
- Lease renewal lifecycle (no dispatcher → 60/30/7-day notifications
  + initiate/accept/confirm flow)
- Late-fee escalation chain (silent late fees → Day 5 SMS / Day 10
  task / Day 30 eviction notice draft)
- Occupancy metric + vacancy alerts (implicit → measured + alertable)
- Landlord approval of tenant-side payment plans and deposit refunds
  (Phase-28 deferral closed)
- Workflow observability (silent failures → `workflow_runs_log` +
  nightly `workflow:health` detector)

Tier 4 cycle 2 (after Tier 4 cycle 1 [TENANT-PORTAL] complete).

## Scheduler table

All schedules use Africa/Nairobi timezone and `onOneServer()`.

| Command | Cadence | Finding | Why this slot |
|---------|---------|---------|---------------|
| `invoices:escalate-overdue` | 00:30 daily | WF-LATE-FEE-1 | After invoices:mark-overdue 00:05 + apply-late-fees 00:10 so the freshly-updated overdue corpus is what gets escalated |
| `workflow:health` | 04:30 daily | WF-CI-2 | After all overnight workflow commands have had a chance to write rows, well before the next 24h window opens |
| `occupancy:audit` | 06:30 daily | WF-VACANCY-1/3 | Between reports:send-scheduled 06:00 and tickets:audit-sla 07:00 |
| `leases:scan-renewals` | 07:30 daily | WF-LEASE-RENEW-1 | Between tickets:audit-sla 07:00 and rent-reminders:dispatch 08:00 |
| `rent-reminders:dispatch` | 08:00 daily | WF-RENT-REMIND-1 | After invoices:automate 06:00 so newly-generated invoices land in the same overnight cycle |

## Event → listener map

Phase29WorkflowSurfaceTest asserts every event below has at least one
registered listener.

| Event | Listener(s) | Action |
|-------|-------------|--------|
| `LeaseRenewalApproaching` | `NotifyOnLeaseRenewalApproaching` | NotificationService::send to tenant + landlord (type=lease_renewal) |
| `VacancyDetected` | `CreateTaskOnVacancyDetected` | LandlordTask 'list_unit' with high priority + 7-day due |
| `OccupancyTargetBreached` | `NotifyOnOccupancyTargetBreached` | NotificationService::send to landlord (type=general) |
| `PaymentPlanApproved` | `NotifyTenantOnPaymentPlanApproved` | NotificationService::send to tenant (type=general) |
| `PaymentPlanRejected` | `NotifyTenantOnPaymentPlanRejected` | NotificationService::send to tenant with rejection_reason |
| `DepositRefundApproved` | `NotifyTenantOnDepositRefundApproved` | NotificationService::send to tenant with final_amount |
| `DepositRefundRejected` | `NotifyTenantOnDepositRefundRejected` | NotificationService::send to tenant with rejection_reason |
| `DepositRefundPaid` | `NotifyTenantOnDepositRefundPaid` | NotificationService::send to tenant with payment_reference |

All listeners implement `ShouldQueue` with `$tries=4` and
`$backoff=[30,60,300,1800]` (Phase-16 RESIL pattern).

## Schema additions

| Table | Columns | Phase finding |
|-------|---------|---------------|
| `rent_reminder_policies` | landlord_id, name, cadence_template enum, offsets_json, channels, is_default, is_active | WF-RENT-REMIND-1 |
| `leases` | reminder_tier enum default 'standard' | WF-RENT-REMIND-2 |
| `lease_renewals` | landlord_id, lease_id, proposed_end_date, proposed_rent_amount_cents, status enum, audit timestamps | WF-LEASE-RENEW-2 |
| `landlord_tasks` | landlord_id, task_type, polymorphic related_to, priority enum, status enum, source_workflow | WF-LATE-FEE-2 + WF-VACANCY-2 |
| `eviction_notice_drafts` | landlord_id, lease_id, tenant_id, related_invoice_ids JSON, total_arrears_cents, draft_body, status enum | WF-LATE-FEE-3 |
| `buildings` | target_occupancy_rate decimal NULL | WF-VACANCY-3 |
| `workflow_runs_log` | landlord_id, workflow_name, polymorphic target, action, metadata JSON, fired_at | WF-CI-2 |

## Ability matrix entries

Phase 29 adds these keys to `App\Support\TenantAbilities::ABILITY_KEYS`
(per the Phase-28 abilities map pattern):

| Key | True when |
|-----|-----------|
| `renewal:respond` | Tenant has an open (status=proposed) lease renewal on their active lease (Phase-29 WF-LEASE-RENEW-3) |

## CI gates

Watchdog test classes under `tests/Feature/Workflow/`:

- `Phase29RentReminderTest` — tiered offset matching, idempotency,
  fallback, opt-out, schedule registration
- `Phase29LeaseRenewTest` — 60/30/7 buckets, propose/accept/confirm
  flow, cross-tenant blocks, renewal:respond ability gate
- `Phase29LateFeeEscalationTest` — Day 5/10/30 levels, never-auto-send
  draft invariant, idempotency
- `Phase29VacancyTest` — OccupancyService aggregation, breach event
  gating, MoveOut transition + future-lease gating
- `Phase29PayApproveTest` — landlord approve/reject/mark_paid flows
  with status transition guards + cross-landlord blocks
- `Phase29WorkflowSurfaceTest` — every Phase-29 scheduler is
  registered + every Phase-29 event has a listener
- `Phase29CiTest` — `workflow_runs_log` writes work; `workflow:health`
  emits gauges + detects silent failures; runbook lists every Phase-29
  scheduler + every event + every test class

## Ops procedures

### Disable a workflow for one landlord
Set `is_active=false` on the relevant `rent_reminder_policies` row.
Per-tenant opt-out goes through the `notification_preferences` matrix
(Phase-28 TENANT-PROFILE-2).

### Inspect why a tenant got a workflow notification
```sql
SELECT * FROM workflow_runs_log
WHERE landlord_id = ? AND fired_at >= NOW() - INTERVAL 7 DAY
ORDER BY fired_at DESC;
```
Filter by `target_type` / `target_id` to scope to a specific Lease /
Invoice / Unit.

### Re-fire a missed run
1. Clear the relevant `Cache::get('rent-reminder:<id>:<offset>')` /
   `lease-renewal:<id>:<bucket>:<YYYY-MM>` / `invoice-escalation:<id>:<level>`
   key from cache.
2. Run the command manually: `php artisan rent-reminders:dispatch`.

## Deferrals

- **Landlord-side Tasks/Index.vue + EvictionNotices/Show.vue UI** —
  Phase 29 ships the LandlordTask + EvictionNoticeDraft data models +
  the auto-creation pipelines, but no landlord-facing UI to triage
  tasks or review eviction drafts. The data is queryable via the
  existing admin tooling; a dedicated UI lands in a follow-up cycle
  (Phase 31 [ONBOARDING] is the natural home — task triage maps to
  the in-app help center).
- **EvictionNoticeDraft sendManual + edit flow** — drafts are
  generated but currently can only be reviewed via DB / future UI.
  The status=sent path waits for the same UI commit.
- **Tenant Vue surfaces for renewal (Tenant/Renewals/Show.vue)** —
  Phase-29 ships the tenant accept/reject controller + the
  `renewal:respond` ability key; the dedicated Vue page is deferred
  to the next cycle to keep Phase 29 scoped to data + automation.

## Memory + lineage

See `project_propmanager.md` Phase 29 paragraph + lineage and
`project_propmanager_path_forward.md` Phase 29 ✅ DONE marker.
