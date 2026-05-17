# Maintenance Runbook

Operator-facing reference for the Phase-49 [MAINTENANCE-DEPTH] surface:
two-stage SLA + per-category overrides + vendor marketplace + parts
inventory + ticket cost attribution.

## Two-stage SLA (response + resolution)

Every ticket carries two SLA windows stamped at creation by
`TicketObserver::creating` via `SlaDefinitionService::resolveFor()`:

- `sla_due_at` — first-response deadline. Breach = a caretaker/landlord
  has not stamped `first_response_at` (any non-tenant activity).
- `resolution_due_at` — resolution deadline. Breach = `status NOT IN
  (resolved, closed, cancelled)` AND `resolved_at IS NULL` after the
  deadline.

`tickets:audit-sla` (daily 07:00 Africa/Nairobi) detects BOTH stages:

- `ticket_sla_breach_count{priority}` gauge — first-response breach.
- `ticket_resolution_breach_count{priority}` gauge — resolution breach.

The `TicketSlaBreached` event carries a `$type` discriminator
(`response` | `resolution`); `NotifyOnTicketSlaBreach` branches
messaging accordingly so on-call can distinguish "caretaker
unresponsive" from "caretaker responsive but slow / vendor delay".

## Per-category overrides

`sla_definitions` table holds the per-(landlord, category, subcategory,
priority) override matrix. NULL on any column means "matches anything"
for that dimension; `landlord_id` NULL = platform-default.

`SlaDefinitionService::resolveFor()` cascade (most specific first):

1. landlord + category + subcategory + priority
2. landlord + category + priority
3. landlord + priority (any category)
4. global + category + subcategory + priority
5. global + category + priority
6. global + priority
7. fallback to `Ticket::SLA_SECONDS` / `RESOLUTION_SLA_SECONDS` constants

Results cached 5 min per resolution tuple — `TicketObserver::creating`
hits this for every new ticket so the hot path stays fast.

`Phase49SlaSeeder` seeds platform defaults: urgent any 4h/24h,
plumbing high 24h/48h, electrical high 4h/12h, structural any
24h/14d, pest_control medium 48h/7d, etc.

### Adding a landlord override

```bash
php artisan tinker
> \App\Models\SlaDefinition::create([
    'landlord_id' => $landlordId,
    'category' => 'issue',
    'subcategory' => 'plumbing',
    'priority' => 'urgent',
    'response_seconds' => 7200,    // 2h
    'resolution_seconds' => 43200, // 12h
    'is_active' => true,
  ]);
```

Cache invalidates naturally after 5 min; manual flush via
`Cache::forget("sla:resolve:{$landlordId}:issue:plumbing:urgent")` if
needed immediately.

## Vendor marketplace

`tickets.vendor_id` (FK `vendors`) links a ticket to an external
contractor. NOT mutually exclusive with `assigned_to` — caretaker can
oversee while vendor executes.

`VendorAssignmentService::assign(Ticket, Vendor, ?$note)`:

1. Validates vendor.landlord_id matches ticket.landlord_id (cross-tenant
   guard).
2. `DB::transaction` updates tickets.vendor_id.
3. Logs `TicketActivity action='vendor_assigned'` with old/new vendor_id.
4. Fires `TicketAssignedToVendor` event.

Route: `POST /tickets/{ticket}/assign-vendor` (landlord-only,
`role:landlord` middleware). Accepts `vendor_id` (must belong to
caller's landlord) + optional `note`.

## Parts inventory

`parts` table holds per-landlord SKUs (name, sku, category,
cost_per_unit_cents, qty_available, reorder_threshold). TenantScope
enforces landlord isolation.

`ticket_parts` pivot captures consumption per ticket:

```
ticket_id | part_id | qty_used | cost_allocated_cents | recorded_by | recorded_at
```

`cost_allocated_cents` is a price snapshot at recording time — if
`cost_per_unit_cents` changes later, the historical attribution stays
correct.

`TicketResolutionService::recordParts(Ticket, [part_id => qty, ...])`:

1. `DB::transaction` wraps the whole batch.
2. For each (part, qty): `lockForUpdate`, validate stock, decrement.
3. Insert pivot row with `cost_allocated_cents = cost_per_unit_cents × qty`.
4. Idempotently upsert a `ticket_costs` row of category `parts` with
   the total (`TicketCostService::recordPartsAggregate`).
5. Throws `ValidationException` on insufficient stock.

### `parts:audit-stock` cron (daily 06:30 Africa/Nairobi)

Walks `Part::belowThreshold()` (qty_available <= reorder_threshold AND
is_active), emits `parts_below_threshold_count{landlord_id}` gauge for
top 50 landlords, fires `parts_below_threshold` sev4 alert via
`AlertFiringRecorder` (threshold 1 — any below-threshold part across
the fleet is a reorder signal).

## Maintenance cost attribution

`ticket_costs` table is the canonical cost record per ticket:

```
id | ticket_id | category [parts|vendor|labor|other] | amount_cents | currency | recorded_by | notes | recorded_at
```

`TicketCostService::recordCost(Ticket, category, amount, ?notes, ?recordedBy)`:

- Validates category in enum + amount >= 0.
- Validates recorder.landlord_id matches ticket.landlord_id (cross-tenant
  guard).
- Inserts row with currency='KES'.

`TicketCostService::summarize(Ticket): array` returns per-category
breakdown + total — used by ticket-detail UI.

`Ticket::totalMaintenanceCost(): int` sums all `ticket_costs.amount_cents`
for the ticket.

### `maintenance:cost-rollup` cron (weekly Sun 05:00 Africa/Nairobi)

Aggregates `ticket_costs` over the last 30 days per landlord, emits
`landlord_maintenance_cost_kes_30d{landlord_id}` gauge for top 50.
Visibility-only (no alert) — ops dashboards consume the gauge.

## Incident playbook

| Symptom | Diagnosis | Fix |
|---------|-----------|-----|
| Sev3 `ticket_resolution_breach_count` fires | Tickets sitting in InProgress past resolution_due_at | `SELECT id, priority, building_id, resolution_due_at FROM tickets WHERE breachedResolutionSla` — inspect for vendor delay, parts stockout, or under-resourced caretaker; reassign or call vendor |
| Sev4 `parts_below_threshold` fires | Stock dipped past reorder | `Part::belowThreshold()->get(['landlord_id', 'name', 'qty_available', 'reorder_threshold'])` — share with landlord for reorder |
| `VendorAssignmentService` throws "Vendor cannot be assigned" | Vendor.landlord_id mismatch | Vendor was created under different landlord (cross-tenant guard); re-create vendor for the correct landlord |
| `recordParts` throws insufficient stock | Caretaker tried to consume more than available | Reorder Part; then retry recordParts |
| `ticket_costs.parts` row out of sync with `ticket_parts` sum | Manual edit bypassed `TicketCostService::recordPartsAggregate` | Re-run `TicketResolutionService::recordParts` for an empty `[]` or recompute manually via `TicketCostService::recordPartsAggregate(ticket, sum)` |

## CI gates

- `Phase49MaintenanceDepthSurfaceTest` — schema, service contracts,
  command exit codes, alert + runbook references.

## Cross-references

- `docs/runbooks/alert-thresholds.md` — sev rows for
  `ticket_resolution_breach_count`, `parts_below_threshold_count`,
  `landlord_maintenance_cost_kes_30d`
- `docs/runbooks/tenant-portal.md` — Phase 28 first-response SLA
- `phase-49-audit-prd.json` — full PRD + audit_closeout
