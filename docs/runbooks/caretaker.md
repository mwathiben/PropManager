# Caretaker Runbook

Operator-facing reference for the Phase-80 [CARETAKER-WORKFLOW-DEEP] surface:
the caretaker's mobile-first task board, the caretaker→landlord escalation
routing, and the landlord-side caretaker performance dashboard.

## Who a caretaker is

A caretaker is a `User` with `role = 'caretaker'` and a `landlord_id`. They are
assigned to buildings via `buildings.caretaker_id` (the `CaretakerAssignment`
table is the accept/decline audit trail). New tickets auto-assign to the
building's caretaker (`TicketObserver`).

## The daily task board

`/my-tasks` (`tasks.index`, role:caretaker) → `Caretaker/TaskBoard.vue`. A
mobile-first board of the caretaker's **own open assigned** tickets, grouped:

1. **Overdue** — `resolution_due_at` in the past, not resolved.
2. **Urgent** — priority urgent, not overdue.
3. **To do** — everything else.

Each card has inline **forward-only** status actions (`tasks.transition`,
assignee-only): open → acknowledged → in_progress → resolved. Resolving stamps
`resolved_at`. A backward/invalid transition is rejected. The board also shows a
"Record water readings" CTA when the water module is enabled (Phase-79
`WaterModuleAccess`) and an "Escalated" chip on tickets the caretaker has an open
escalation on.

## Escalation routing (caretaker → landlord)

When a caretaker is stuck, they **escalate** (`tasks.escalate`) with a reason
(preset from `config('maintenance.escalation_reasons')` + free text).

`App\Services\Maintenance\TicketEscalationService`:

- `escalate(Ticket, ?User, reason)` — guards: assignee-only (request), ticket not
  resolved/closed/cancelled, no existing **open** escalation (idempotent). Sets
  `escalated_at/by` + `escalation_reason`, logs `TicketActivity::ACTION_ESCALATED`,
  dispatches `TicketEscalated` → `NotifyLandlordOnTicketEscalation` (in-app to the
  landlord). An **open escalation** = `escalated_at` set, `escalation_acknowledged_at` null.
- `acknowledge(Ticket, User landlord)` — sets `escalation_acknowledged_at/by`, logs
  `ACTION_ESCALATION_ACKNOWLEDGED`. Idempotent.

**Landlord side** (the escalation queue):

- The landlord dashboard shows an "Escalated Tickets" action card (count of open
  escalations) linking to the tickets list filtered `?escalated=1`
  (`Ticket::scopeEscalated`).
- A `navBadges.escalations` count is also computed.
- The ticket Show page shows an escalation banner (reason + who) with an
  **Acknowledge** button (`tickets.escalation.acknowledge`, role:landlord).
- **Reassigning** the ticket to another caretaker (`tickets.assign`) also clears
  the open escalation — the landlord has acted on it.

### Opt-in SLA-breach auto-escalation

`config('maintenance.auto_escalate_on_sla_breach')` (default false). When true,
`AutoEscalateOnSlaBreach` listens to `TicketSlaBreached(type=resolution)`: if the
ticket is caretaker-assigned and not already escalated, it auto-escalates
(system actor, reason `sla_breach`) so the landlord has one escalation queue.
Idempotent.

## Caretaker performance (landlord-side)

`/maintenance/caretaker-performance` (`maintenance.caretaker-performance`,
role:landlord), linked from the Maintenance hub →
`Maintenance/CaretakerPerformance.vue`.

`App\Services\Maintenance\CaretakerPerformanceService::forLandlord(landlordId, windowDays=90)`
mirrors `VendorPerformanceService` — batched grouped queries keyed by
`tickets.assigned_to` over the landlord's caretakers, landlord-scoped, no N+1:

| Metric | Definition |
| --- | --- |
| `within_sla_pct` | resolved-in-window where `resolved_at <= resolution_due_at`, / resolved-with-due |
| `avg_resolution_hours` | mean `created_at → resolved_at` over resolved-in-window |
| `avg_first_response_hours` | mean `created_at → first_response_at` over resolved-in-window |
| `open_count` / `open_overdue` | currently open / open past resolution SLA |
| `water_readings_recorded` | approved readings `recorded_by` the caretaker in the window |
| `escalations_raised` | escalations the caretaker raised in the window |

`first_response_at` is stamped by `TicketActivity` on the first non-tenant
activity (so a caretaker's acknowledge/comment/status-change counts).

### Rollup gauge

`caretaker:performance-rollup` (weekly Sun 05:10 Africa/Nairobi, in the Sunday
rollup cluster after property:benchmark-rollup) emits
`landlord_caretaker_within_sla_pct{landlord_id,caretaker_id}` — visibility-only,
no alert. Caretakers with no resolved tickets in the window (null %) are skipped.

## Common operator tasks

| Symptom | Where to look |
| --- | --- |
| Caretaker can't transition a ticket | Forward-only + assignee-only. Check `tasks.transition` (assigned_to === caretaker) and the status order. |
| Escalation didn't notify the landlord | `NotifyLandlordOnTicketEscalation` is queued; check the queue + the landlord exists. |
| Escalation stuck in the queue after the landlord acted | Acknowledge or reassign clears it; check `escalation_acknowledged_at`. |
| Double escalation | Not possible — `escalate()` is idempotent on an open escalation. |
| Auto-escalation not firing on SLA breach | It's opt-in: set `MAINTENANCE_AUTO_ESCALATE_ON_SLA_BREACH=true`; only resolution breaches on caretaker-assigned tickets escalate. |
| Performance % looks off | within-SLA needs `resolution_due_at`; tickets with no due date are excluded from the %. |
