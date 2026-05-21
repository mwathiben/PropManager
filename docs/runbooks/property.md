# Property Runbook

Operator-facing reference for the Phase-78 [PROPERTY-DEPTH] surface: the
property tier (portfolio dashboards above the building level), per-amenity
detail, the active-property switcher, and cross-property benchmarking.

## The property tier

A landlord's portfolio is `Property → Building → Unit → Lease`. Phase 78 adds
a first-class property tier *above* buildings:

- `GET /properties` (`properties.index`) — every property with portfolio
  metrics (building/unit counts, occupancy %, monthly rent roll, arrears).
- `GET /properties/{property}` (`properties.show`) — single-property
  dashboard: KPI cards + NOI row + per-building drill-down.
- `GET /properties/current` (`properties.current`) — renders the resolved
  *active* property (see switcher below); redirects to `properties.index`
  when the landlord has no properties.
- `GET /properties/benchmark` (`properties.benchmark`) — cross-property
  ranking.

All routes are gated `role:landlord,caretaker`. `properties.switch` is
further restricted to `role:landlord` (active-property is a per-landlord
concept). Every controller action also runs
`abort_unless($property->landlord_id === getLandlordId(), 404)` as
defense-in-depth on top of `TenantScope`'s global landlord scope.

## Metrics (PropertyMetricsService)

`PropertyMetricsService` is the single source of per-property aggregates.
`forLandlord(int)` returns one row per property; `forProperty(Property)`
returns one row. All sums are batched in grouped queries keyed by
`property_id` (no per-property loops, no N+1), strictly scoped by
`buildings.landlord_id`:

- `occupancy_pct` = occupied units / total units.
- `monthly_rent_roll` = SUM(`leases.rent_amount`) over active leases.
- `outstanding_arrears` = SUM(`total_due - amount_paid`) over non-voided
  invoices where `amount_paid < total_due`.

Money in this subsystem is **DECIMAL FLOAT**, not cents-integer (matches the
building/property subsystem convention).

## Amenity detail (AmenityDetailService)

`building_amenity_details` holds per-amenity operational detail (quantity,
provider, account ref, monthly cost) for the amenities a building has
selected. `AmenityDetailService::sync(Building, array)`:

- Allow-lists keys to `Building::getAllAmenityKeys()` ∩ the building's
  currently-selected amenities — unknown or deselected keys are rejected.
- Prunes detail rows for amenities that have been deselected.
- `updateOrCreate` keyed by `(building_id, amenity_key)`; `landlord_id`
  copied from the building.

Wired into `BuildingController::updateSettings`. `Building::getActiveAmenities()`
merges the detail (guarded by `relationLoaded('amenityDetails')` to avoid
N+1) so `Buildings/Show.vue` can render `× quantity · provider`.

## Active-property switcher

`users.active_property_id` (nullable FK, `nullOnDelete`) stores the
landlord's chosen active property. `ActivePropertyResolver::resolve(User)`:

1. the stored `active_property_id` when it still exists and is owned, else
2. the landlord's first property by id, else
3. `null` (no properties yet).

Caretakers resolve via `landlord_id`. The shared `propertySwitcher` Inertia
prop (landlord-only) feeds `Components/PropertySwitcher.vue` in the topbar;
it is hidden when the landlord has fewer than two properties. The shared
prop derives `active_id` from the already-loaded option rows (no extra
query on the hot path).

## Benchmark (PropertyBenchmarkService)

`PropertyBenchmarkService::forLandlord(int)` ranks every property against the
rest of the portfolio on three comparable yardsticks:

- `occupancy_pct` (from PropertyMetricsService)
- `noi_margin` (from `NoiService::byProperty`)
- `gross_yield` = annualised rent roll / `estimated_value`

Each yardstick gets a **percentile rank** (0–100, higher is better — the
fraction of *other* properties this one beats). With a single property there
is nothing to compare against, so percentiles are `null` and the property's
overall `rank` is 1. The overall rank orders by the mean of the available
percentiles. Portfolio medians are returned for the dashboard header.

Two methodology notes operators should know:

- **Periods differ across yardsticks.** `gross_yield` is *forward* — current
  active-lease rent roll annualised (point-in-time). `noi_margin` is
  *trailing-12-month* (from `NoiService::byProperty`'s default window). A
  property just leased up shows a high gross_yield but a still-depressed
  trailing margin; this is expected, not a data error.
- **Ranking weighting varies with data completeness.** The overall rank is the
  mean of the *available* percentiles, so a property with no `estimated_value`
  (hence null gross_yield) is ranked on occupancy + margin only. Set
  `estimated_value` on every property for apples-to-apples ranking.
- **Two "occupancy" numbers, deliberately.** The `landlord_portfolio_occupancy_pct`
  gauge is **unit-weighted** (sum occupied / sum units across the portfolio —
  the standard portfolio figure). The dashboard's `avg_occupancy_pct` /
  `median_occupancy_pct` are **per-property** (unweighted across properties).
  They diverge when properties differ greatly in unit count.

### Rollup gauge

`property:benchmark-rollup` (weekly Sunday 05:05 Africa/Nairobi, in the
Phase-49 Sunday cost/rollup cluster) emits the
`landlord_portfolio_occupancy_pct{landlord_id}` Prometheus gauge.
Visibility-only — **no alert** (mirrors Phase-49 `maintenance:cost-rollup`:
gauge + dashboard, no paging). Landlords with zero units are skipped.

## Common operator tasks

| Symptom | Where to look |
| --- | --- |
| Property metrics look stale/wrong | `PropertyMetricsService::compute` — check the grouped joins are landlord-scoped; verify lease `is_active` / invoice `voided_at`. |
| Amenity detail not showing on a building | Confirm the amenity is still *selected* on the building; `sync` prunes detail for deselected amenities. Confirm `Buildings/Show.vue` eager-loads `amenityDetails`. |
| Switcher missing from topbar | Landlord-only, and hidden with < 2 properties. Check `HandleInertiaRequests::getPropertySwitcher`. |
| Benchmark percentiles all null | Only one property in the portfolio — expected. |
| `landlord_portfolio_occupancy_pct` gauge absent | Run `property:benchmark-rollup` manually; landlords with zero units are skipped by design. |
