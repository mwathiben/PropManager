# Dashboard runbook

The landlord `/dashboard` is the single most-trafficked screen in the product. Several years of growth have layered features on it (Phases 27, 36, 37, 50, 55+); this runbook is the operator-facing map of which subsystem owns which surface and where to look first when something looks wrong.

## Surfaces and ownership

| Surface | Owner | Lineage |
|---|---|---|
| Action items / financial metrics / arrears aging | `DashboardService::calculateLandlordMetrics` | Phase 22 PERF-Q7 (whereIn over EXISTS) |
| Recent payments card | `DashboardService::calculateLandlordMetrics` lines ~680-710 | Phase 17 MONEY-1 (payment_date `date` cast), Phase 17 MONEY-4 (voided filter), **Phase 55 RECENT-PAYMENTS** |
| Recent tickets / expiring leases | `DashboardService::calculateLandlordMetrics` | Phase 50 LANDLORD-DASHBOARDS-1 layout JSON |
| Payment detail page | `App\Http\Controllers\PaymentDetailController::show` | **Phase 55 PAYMENT-DETAIL** (replaces the prior invoices.show fallthrough) |
| Building filter chip | `Dashboard.vue` chip block + `DashboardService::buildLandlordDashboardData` `allBuildingsMode` | **Phase 55 DASHBOARD-FILTERS** |
| Lease-state badge on recent-payments rows | `DashboardService` eager-load with `withTrashed` + setAttribute('lease_state', …) | **Phase 55 LEASE-STATE-BADGE** |
| Widget ordering (bottom-row cards) | `App\Http\Controllers\DashboardPreferenceController` + `landlord_dashboards` row `slug='main_dashboard'` | Phase 50 layout JSON primitive, **Phase 55 WIDGET-ORDERING** |
| Growth signals (engagement / referrals / usage ratios) | `InsightDashboardService::landlordSummary` | Phase 36 INSIGHT-LANDLORD-1 |

## Phase 55 — DASHBOARD-DEPTH (2026-05-18)

Phase 55 closes a user-reported recent-payments regression and adds four polish surfaces. Closeout: see `phase-55-audit-prd.json` for the 18 findings (5H/9M/4L).

### RECENT-PAYMENTS

Three 1-line patches to `app/Services/DashboardService.php`:

1. `Lease::withTrashed()->whereIn(...)` at the metricsLeaseIds derivation — surfaces payments tied to soft-deleted leases (e.g., the final settlement when a tenant moves out).
2. `->where('is_voided', false)` on the recent-payments Payment query — mirrors the Phase-17 MONEY-4 contract already applied to MTD revenue.
3. `->orderBy('payment_date', 'desc')->orderBy('created_at', 'desc')` — payment_date is the user-perceived "when did this happen" date; created_at is a tiebreaker for same-day entries. The previous `orderBy('created_at')` buried back-dated payments under fresher rows.

The same three-line fix applies to `calculateTenantMetrics` (per-lease scope).

### PAYMENT-DETAIL

`GET /payments/{payment}` → `App\Http\Controllers\PaymentDetailController::show` → `Pages/Payments/Detail.vue`.

- Policy: `PaymentPolicy::view` (landlord/caretaker/tenant ownership).
- The lease eager-load uses `withTrashed()` so the page works for ended leases.
- The payload computes `lease_state ∈ {active, ended, soft_deleted, unknown}` once on the server.

Recent-payments rows in `Dashboard.vue` now route to `payments.detail.show` instead of `invoices.show`.

### DASHBOARD-FILTERS

`/dashboard?building_id=all` (or missing when ≥2 main buildings exist) aggregates metrics landlord-wide across every main building (and its wings) of the active property. `/dashboard?building_id=N` scopes to that building (existing behavior).

- `DashboardService::getCrossBuildingMetricsContext` derives `metricsBuildingIds` + `metricsUnits` across all main+wing ids.
- The Inertia payload exposes `allBuildingsMode: boolean` so the Vue chip can render.
- Dashboard.vue shows a chip "All buildings" (indigo) or "Building X" with x-to-clear (emerald) above Recent Payments, only when the landlord has ≥2 main buildings.

### LEASE-STATE-BADGE

Each recent-payments row carries `lease_state` from the server. Dashboard.vue renders an inline badge: emerald for active (suppressed; default), gray for ended, rose for soft_deleted. Badges have `aria-label` for screen readers and a `data-testid="lease-state-badge"` hook for CI.

### WIDGET-ORDERING

Reuses the Phase-50 `landlord_dashboards.layout` JSON primitive with a reserved `slug='main_dashboard'` row.

- `PATCH /dashboards/preferences` → `DashboardPreferenceController::update` validates against `ALLOWED_WIDGETS` and upserts via `updateOrCreate(landlord_id, slug)`.
- `DashboardService::resolveWidgetOrder` reads the row, sanitises against the allow-list, appends any missing canonical ids so a partial row never breaks the dashboard, and falls through to the canonical default when no row exists.
- Dashboard.vue uses native HTML5 drag-and-drop (no library) and CSS `order` to reflow the existing 3-column grid; on drop, posts the new order via `router.patch` with `preserveState/preserveScroll`.

## Where to look first

| Symptom | Suspect | Verify |
|---|---|---|
| Recent payment marked paid but not on dashboard | RECENT-PAYMENTS-1/2/3 closure | Confirm `payment.is_voided=false`, the lease is reachable via metricsLeaseIds, and `payment_date` is within the top-5 by date |
| Cross-tenant landlord sees another landlord's payment | `PaymentPolicy::view` | Tested by Phase55PaymentDetailTest; assert 403-or-404 |
| Building filter chip is missing | Only renders for ≥2 main buildings; check `buildings.length` | `Dashboard.vue` chip block |
| Widget order resets on refresh | LandlordDashboard row missing or invalid layout | `landlord_dashboards.where(landlord_id, slug=main_dashboard).first()` |
| `widgetOrder` ignored on render | Layout has invalid ids | `resolveWidgetOrder` sanitises against `ALLOWED_WIDGETS` |

## Tests

- `tests/Feature/Dashboard/Phase55RecentPaymentsTest.php` — 3 tests / 5 assertions
- `tests/Feature/Dashboard/Phase55PaymentDetailTest.php` — 5 tests / 46 assertions
- `tests/Feature/Dashboard/Phase55DashboardFiltersTest.php` — 3 tests / 9 assertions
- `tests/Feature/Dashboard/Phase55LeaseStateBadgeTest.php` — 3 tests / 6 assertions
- `tests/Feature/Dashboard/Phase55WidgetOrderingTest.php` — 6 tests / 10 assertions
- `tests/Feature/Dashboard/Phase55DashboardDepthSurfaceTest.php` — cross-category presence watchdog
