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

---

## Phase 54 [MAINTENANCE-DEPTH-2] extensions (2026-05-18)

Closes the four Phase 49 sub-scope deferrals (A3.1-A3.4 in pending-path
registry) plus adds VENDOR-ONBOARDING (net-new).

### A3.1 → VENDOR-NOTIFICATIONS

`App\Listeners\NotifyVendorOnAssignment` is the listener Phase 49
deferred. ShouldQueue + `$tries=4` + `$backoff=[30,60,300,1800]`
Phase-16 RESIL pattern. Auto-discovered by typed
`handle(TicketAssignedToVendor)` — no explicit registration. Skips
silently when `vendor.email` is null.

`App\Mail\VendorAssignmentMailable` queued + afterCommit. Locale
resolved via `ticket.landlord->preferredLocale()` (vendors are NOT
Users so `HasLocalePreference` doesn't auto-fire); falls back to
`config('app.fallback_locale')` when landlord locale is unknown.

i18n: `lang/{en,sw,ar}/maintenance.php` new namespace.
`vendor_assigned.*` keys identity-parity across all three locales;
`ar/` carries `[TODO-ar]` placeholders matching the Phase 44
shrink-only baseline.

### A3.2 → SLA-LANDLORD-UI

Routes under **`/sla`** with `role:landlord` middleware. NOT
`/admin/sla` — `/admin/*` is super-admin only. Platform-default rows
(`landlord_id NULL`) stay read-only from this surface; they're
authored elsewhere.

Landlord can:
- View their own overrides + the read-only global cascade.
- Create / update / delete only their own rows (`SlaDefinitionPolicy`
  enforces `landlord_id === user.id`).

**Cache invalidation contract**: `SlaDefinitionService::resolveFor`
embeds a per-landlord version stamp (`sla:ver:{landlord|global}`) in
the cache key. `SlaDefinitionObserver::saved/deleted` bumps that
counter so the next resolve computes a fresh key — landlord saves an
override and sees it immediately, not after a 5-minute lag.

### A3.3 → PARTS-REORDER

`parts:reorder-suggest` cron runs daily 06:45 Africa/Nairobi (after
`parts:audit-stock` 06:30, before `tickets:audit-sla` 07:00). Walks
`Part::belowThreshold` across all landlords; groups by inferred
vendor (latest `ticket_parts → tickets.vendor_id`, NULL when no
history); idempotently `firstOrCreate`s a `DraftPurchaseOrder` per
(landlord, vendor, draft) via the `dpo_unique_open_per_vendor`
constraint; `updateOrCreate`s lines per part_id. Re-runs do NOT
duplicate.

Suggested qty: `max(1, reorder_threshold * 2 - qty_available)`.

`/parts/purchase-orders` (landlord-only) lists drafts with confirm /
cancel actions. Confirm flips status to `sent`; cancel to
`cancelled`. Visibility-only gauge
`draft_purchase_orders_pending_count{landlord_id}` for ops.

### A3.4 → COST-UI

`Tickets/Show.vue` sidebar Cost card (landlord + caretaker view;
tenant view omits the prop entirely). 4-segment proportional bar
(parts/vendor/labor/other in indigo/emerald/amber/rose) + per-
category breakdown formatted KES en-KE via `Intl.NumberFormat`.

Manual cost entry modal POSTs to `tickets.costs.store` (landlord-
only, validates `category in [vendor, labor, other]` — `parts` is
auto-recorded by Phase 49 `TicketResolutionService::recordParts` and
rejected here to keep a single source of truth). Each store writes
a `TicketCost` row + a `TicketActivity action=cost_recorded` audit
entry inside `DB::transaction`.

`TicketPolicy::createCost` requires landlord ownership; caretakers
view but cannot mutate.

### VENDOR-ONBOARDING (net-new)

`App\Observers\VendorObserver::created` mints a 7-day signed URL via
`URL::signedRoute('vendor.profile.edit', ...)` and queues
`VendorCreatedMailable` to `vendor.email`. Skipped silently when
`vendor.email` is null.

`/v/profile/{vendor}` GET + PATCH live OUTSIDE the auth middleware
group, under `signed + throttle:invitation`. Vendor is standalone
(no User row, no auth). The signed URL IS the auth — Laravel's
`signed` middleware verifies on each request.

Allow-listed mutations: `contact_person`, `phone`, `address`,
`notes`. `landlord_id`, `email`, `name` are explicitly NOT in the
validator so a token-holder cannot pivot to identity / billing
mutations.

`Pages/Vendor/Profile.vue` is a standalone branded form (no
`AuthenticatedLayout`) — vendors are not logged in.

### CI gates

- `Phase54VendorNotificationsTest` (8 / 43)
- `Phase54SlaLandlordUiTest` (7 / 25)
- `Phase54PartsReorderTest` (7 / 22)
- `Phase54CostUiTest` (6 / 38)
- `Phase54VendorOnboardingTest` (7 / 26)
- `Phase54MaintenanceDepth2SurfaceTest` — cross-category presence map

## Phase 75 [MAINTENANCE-DEPTH-3] extensions (2026-05-21)

### VENDOR-PERF → vendor performance comparison

`VendorPerformanceService::forLandlord(landlordId, windowDays=90)` returns,
per active vendor, within-SLA %, avg resolution hours, open overdue, resolved
count, and cost per ticket (cost via `ticket_costs ⋈ tickets`, soft-delete
aware). Surfaced at `/maintenance/vendor-performance` (landlord-only, sortable,
30/90/365-day window) and linked from the maintenance hub.

### VENDOR-ROUTING → specialties + pool + opt-in auto-route

`vendor_specialties` (allow-list gated to `Ticket::issueSubcategories()`,
`Vendor::syncSpecialties()`). `VendorAssignmentService::suggestPool(ticket)`
ranks specialty-matching active vendors by within-SLA % then fewest open
overdue (falls back to all active when no specialty match).
`autoAssign(ticket)` is config-gated (`maintenance.auto_route_vendors`,
default off), never overrides a vendor already set, no-ops on an empty pool;
fired from `TicketObserver::created` afterCommit in a try/catch.

### PARTS-PRICING → price history + suppliers + pricing UI

`part_price_history` is append-only: `PartObserver` writes a row on Part
create and whenever `cost_per_unit_cents` changes (only). `part_suppliers`
(unique `part_id,vendor_id`, both FKs landlord-scoped) drives
`Part::cheapestSupplier()` / `fastestSupplier()`. `/parts/pricing`
(landlord-only) shows a per-part cost sparkline + supplier comparison with
add/remove; suppliers mutate via `PartSupplierController` (route-bound part
404s a foreign part, `vendor_id` gated by a landlord-scoped `Rule::exists`).

### PARTS-FORECAST → lead-time-aware reorder

`PartUsageService::dailyRate(part, windowDays=90)` = Σ `ticket_parts.qty_used`
in the window ÷ days (landlord-scoped via the tickets join); `dailyRatesFor()`
batches it per landlord.

`parts:reorder-suggest` now triggers on the **effective threshold** —
`reorder_threshold + ceil(lead_time_days × dailyRate)` — where `lead_time_days`
comes from the cheapest known supplier, else `maintenance.default_lead_time_days`
(7). A part still above its static threshold but projected to run out before a
replacement arrives is ordered early. Each `draft_purchase_order_lines` row
records `trigger_reason` (`static` vs `lead_time_buffer`) and
`projected_stockout_at` (`today + floor(qty_available ÷ dailyRate)`, null when
unused). Suggested qty grows by the lead-time buffer:
`max(1, reorder_threshold × 2 − qty_available + ceil(lead_time × rate))`.
Idempotency (per-`(landlord, vendor, draft)` upsert + per-`part_id` lines) is
preserved.

Gauges: `parts_usage_rate_per_day{landlord_id, part_id}` (from
`parts:audit-stock`, below-threshold parts only) and
`parts_predicted_stockout_count{landlord_id}` (from `parts:reorder-suggest`,
count of lead-time-buffer parts). `/parts/purchase-orders` shows each line's
trigger reason + projected stockout date.

### CI gates

- `Phase75VendorPerformanceTest`
- `Phase75VendorRoutingTest`
- `Phase75PartsPricingTest`
- `Phase75PartsPredictTest`
- `Phase75MaintenanceDepth3SurfaceTest` — cross-category presence map
