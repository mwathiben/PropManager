# Water Runbook

Operator-facing reference for the Phase-79 [WATER-HUB] surface: the conditional
water module, the role split (caretaker records / landlord reviews), the hub tab
map, and nav reachability.

## The water module is conditional

Water is **not** always on. It is visible to a landlord (and their caretakers
and tenants) only when BOTH hold:

1. the subscription **plan permits** water billing (`plan.water_billing_enabled`), AND
2. the landlord **actually charges** for water — a `water_billing_type` of
   `consumption` or `flat_rate` on the global `PaymentConfiguration` OR on any
   of the landlord's buildings.

The single source of truth is `App\Services\Water\WaterModuleAccess`:

- `enabledFor(User)` — resolves the landlord (landlord → self; caretaker/tenant
  → their landlord) and returns the combined gate. Super-admins always pass.
- `enabledForLandlord(int)` — the landlord-keyed form (cached 300s).
- `forget(int)` — busts the cache; called from every water-config write path
  (onboarding `processFinancial`, `WaterSettingsController::update`,
  `BuildingController::updateWaterSettings`).

`HandleInertiaRequests::getFeatureAccess` overrides the shared
`featureAccess.water_billing` flag with this resolver, so the nav (landlord,
caretaker, tenant) hides/shows automatically.

### Enabling water for a landlord who chose "No water billing"

The `water.module` middleware gates the hub + readings, so those dead-end while
disabled. The **enable points are deliberately NOT gated**: onboarding step 5
(financial), the global `/water/settings` page, and a building's
`buildings.water-settings`. Set a `water_billing_type` of `consumption` or
`flat_rate` there and the module turns on (cache busts immediately).

## Role split — caretaker records, landlord reviews

The hub (`/water`, `water.hub`) is role-aware (`WaterHubController::index`):

| Role | Default tab | Tabs | Can input? | Can approve/reject? |
| --- | --- | --- | --- | --- |
| Caretaker | Record readings | Record · History · Settings | Yes | No |
| Landlord | Review | Review · History · Settings | No (no input tab) | Yes |
| Tenant | — (read-only page) | `/tenant/water` list | No | No |

- The caretaker is the **sole inputter**. The "Record readings" tab links to
  the input form (`readings.index`).
- The landlord **only reviews** — the Review tab lists pending readings with
  approve/reject (landlord-only, enforced by `ApproveWaterReadingRequest` +
  `WaterReadingPolicy`). The landlord has no input affordance in the hub.
- A landlord cannot force `?tab=readings` and a caretaker cannot force
  `?tab=review` — the controller swaps to the role-correct tab.

### The landlord dashboard carries no water widget

Water-reading review is a hub concern. The landlord dashboard no longer renders
(or computes) a "Pending Readings" action item — that lived on the dashboard and
was moved into the hub Review tab. The caretaker dashboard keeps its water
widgets, gated on the same `WaterModuleAccess` rule.

## Tenant visibility

When the landlord charges for water, the tenant gets a read-only
`/tenant/water` (`tenant.water`) page listing the **approved** readings + charges
for the unit they rent, plus a "My Water" nav entry. Both are gated by
`water.module` / `featureAccess.water_billing`, so a tenant of a non-charging
landlord sees neither.

## Nav reachability guard

Phase 79 also added an orphan-page guard (the 2026-05-21 "can't navigate to the
new hubs" report). `InertiaPageReachabilityTest` proves a page *renders*;
`scripts/nav-audit.mjs` + `tests/Feature/Phase79NavReachabilityTest` prove a
page is *linked*.

- `node scripts/nav-audit.mjs` — flags any named GET route that renders an
  Inertia page but is never referenced via `route('<name>')` under
  `resources/js`, minus `scripts/nav-audit-baseline.json` (the shrink-only set
  of pages reached another way: hub tabs `?tab=`, middleware redirects, the
  separate vendor portal, settings sub-nav, external/API entry).
- New orphan → audit fails. Wiring a baselined page → remove it from the
  baseline (shrink-only ratchet).

### Two pre-existing crashers fixed

`WaterHubController` filtered on `is_approved` and `has_water_meter` — **columns
that never existed** — so the readings/history tabs 500'd (a real cause of "I
can't open the water hub"). Both now use the `status` enum and drop the
non-existent meter filter; the hub also reads buildings landlord-wide instead of
just the first property.

## Common operator tasks

| Symptom | Where to look |
| --- | --- |
| Landlord can't see the Water hub | They must charge for water (`water_billing_type` consumption/flat_rate) AND their plan must allow it. Check `WaterModuleAccess::enabledForLandlord`. |
| Nav didn't update after enabling water | Cache is 300s; the write paths call `WaterModuleAccess::forget`. If stale, confirm the write path ran. |
| Caretaker sees Review / landlord sees Record | Shouldn't happen — `WaterHubController::index` forces the role-correct tab. Check the role resolution. |
| New page unreachable from the UI | Run `node scripts/nav-audit.mjs`; wire a `route()` link or baseline it. |

## Phase 86 — meter foundation

Readings now hang off a first-class **`Meter`** (`water_meters`), not directly off
a unit. One active meter per unit is the invariant the biller relies on; the
backfill created one meter per unit with history (initial reading taken from the
unit's earliest `previous_reading`).

| Concept | Where |
| --- | --- |
| Meter entity + lifecycle states | `App\Models\Meter` + `App\Enums\MeterStatus` (active/inactive/faulty/replaced/decommissioned) |
| Non-zero install baseline | `meters.initial_reading` — a meter's first read is measured from this, not 0. Set it when registering the meter. |
| Register / replace / decommission | `MeterController` (landlord-only) → `/water/meters`, linked from the landlord hub overview |
| Replacement continuity | `MeterReplacementService::replace` — records the old meter's closing read, retires it, and starts the successor from its OWN baseline. It's an explicit event; never infer a swap from a low reading. |
| Per-meter, baseline-aware consumption | `WaterReadingService::processReading` — previous = the meter's last reading or its baseline |
| Spike flag | a reading whose consumption exceeds `propmanager.water.spike_multiplier` (default 5) × the meter's trailing average is flagged `is_anomalous` (non-blocking) and shown with an amber chip in the review queue |
| Caretaker role-split | the caretaker water hub no longer shows the **Settings** tab; water billing config is landlord-only (`WaterHubController` bounces `?tab=settings`, `WaterSettingsController::index` is 403 for caretakers) |

### Operator notes
- A unit can have only one **active** meter — registering a second is rejected; use **Replace** to swap.
- A `replaced`/`decommissioned` meter cannot be decommissioned again (keeps the replacement chain intact).
- `meter.utility_type` is `water` for now but the model is built to extend to other utilities later; do not implement electricity/gas off it yet.

## Phase 87 — tariff engine

`WaterTariffService` turns consumption into a charge with real water-tariff
depth. **Non-destructive**: with nothing configured the result equals the old
flat `rate × consumption` (or `flat_water_rate`).

| Concept | Where |
| --- | --- |
| Tiered/block rates | `tiered_tariffs` json (`[{from,to,rate}]`, last band `to` blank = open) on payment_configurations / buildings; `WaterTariffService::computeConsumptionCharge` |
| Standing charge / minimum bill / sewerage % / VAT % | `water_standing_charge` / `water_minimum_charge` / `water_sewerage_percent` / `water_vat_percent`; `WaterTariffService::assembleWaterCharge` (subtotal = base + standing; + sewerage%; + VAT%; floored at minimum) |
| Water source | `water_source` (borehole\|county\|mixed) — stored now, used by Phase 91 intelligence + Phase 92 compliance |
| Reading cost | `WaterReadingObserver` → `WaterTariffService::costForReading` (tiered) |
| Invoice water charge | `InvoiceService::calculateWaterCharges` wraps the base in `assembleWaterCharge` (per-period fixed components) |
| Config resolution | building override → landlord `PaymentConfiguration` → default (mirrors WaterRateService); building NULL = inherit |
| Editing | shared `Components/Water/WaterSettingsForm.vue` (global + per-building), landlord-only |

### Resequenced (deliberate, not dropped)
- **WaterConnection** (universal billable entity) → Phase 94 (water clients) — introducing it earlier would rebuild the biller twice.
- **Apportioned billing mode + common-area split**, **borehole production-cost capture**, **effective-dated tariff scheduling** → dedicated later water phases.
- Per-building tiered-band editing in the UI is deferred (backend supports it; v1 exposes global bands + per-building flat-rate/levy overrides).

## Phase 88 — reading cycle (read → review → bill)

Automates the monthly water cadence and — critically — guarantees water revenue
is never silently dropped (a reading left pending is excluded from invoicing).

| Concept | Where |
| --- | --- |
| Read-date + review window config | `water_reading_day` (day-of-month) + `water_review_days` on payment_configurations / buildings (building null=inherit; default review 7d); shared settings editor |
| Caretaker reading reminder | `water:reading-reminders` (daily 07:45) → on a consumption building's reading day, notifies its caretaker (`water_reading_due`), idempotent per building+month |
| Landlord review reminder | `water:review-window` (daily 05:45) → buildings with pending readings inside the window nudge the landlord (`water_review_due`), once per building+month |
| **Auto-approve safety** | same command auto-approves any reading still pending past the review window (`WaterReading::autoApprove`, `auto_approved=true`, reviewed_by null) + audits `TenantActivity` (water_reading_auto_approved) + one escalation per landlord. **This is why a never-reviewed reading still bills instead of hanging forever.** |
| Re-read | `WaterReadingController::requestReread` (landlord-only, non-invoiced) reopens a reading to pending + re-prompts the caretaker; button on the review tab |

### Operator notes
- Set `water_reading_day` a few days before `invoice_generation_day`, with `water_review_days` short enough to close before invoicing, so approved readings are picked up that cycle. A reading recorded too late auto-approves and bills the NEXT cycle (never lost).
- The review window is measured from when a reading was **recorded** (created_at + review_days), not a fixed calendar date — robust to late reads.
- New notification types `water_reading_due` / `water_review_due` are IMPORTANT urgency (email + in-app default-on).

## Phase 89 — historical import

Backfill historical water readings from a landlord's CSV **or Excel** sheet via
the existing imports feature (`/imports`, type `water_readings`; linked from the
landlord water hub as "Import history").

| Concept | Where |
| --- | --- |
| Importer | `ImportService::importWaterReadings` (dispatched by `processImport`) |
| File types | CSV/TXT (native) + **.xlsx/.xls** (PhpSpreadsheet via `parseRows`/`parseSpreadsheet`) |
| Template columns | Unit Number, Reading Date, Previous Reading, Current Reading, **Consumption (opt)**, **Cost (opt)** — download from the imports page |
| Never re-bills | imported readings are `status=approved` + **`is_invoiced=true`** (already-billed history), so `InvoiceService::calculateWaterCharges` excludes them |
| Faithful values | optional Consumption/Cost preserved as-is (`Model::withoutEvents`); absent Cost = consumption × current effective rate (estimate) |
| Idempotent | a row whose (meter, reading_date) already exists is **skipped** (`summary.skipped_duplicates`), so re-uploading the same sheet is safe |
| Meter link | each row resolves `Meter::resolveActiveForUnit` so imported history is meter-keyed like live readings |

### Operator notes
- Rows map to a unit by **Unit Number** (scoped to the landlord). Water clients (Phase 94) will extend the importer to map by water-line/client.
- Imported readings are history for analytics (Phase 91) — they never appear in the review queue and never bill.
