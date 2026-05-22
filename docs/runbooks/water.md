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
