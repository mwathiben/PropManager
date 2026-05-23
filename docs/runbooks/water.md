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

## Phase 90 — arrears + disconnect/reconnect

Water arrears tracking + a service-disconnection lever for non-payment.

| Concept | Where |
| --- | --- |
| Disconnect state | water_meters.disconnected_at + disconnect_reason (separate fields — meter stays status=active so readings/billing still resolve); Meter::isDisconnected/scopes |
| **THE CAVEAT** | only a UNIT meter (unit_id set, no parent_meter_id, no sub-meters) can be disconnected — `Meter::isUnitMeter()`; a shared/main meter is rejected (would cut the whole building) |
| Actions | MeterController disconnect/reconnect (landlord-only) on the Meters page; TenantActivity audit (water_meter_disconnected/reconnected) |
| Reconnection fee | `water_reconnection_fee` config (landlord + building, inherit-aware); on reconnect a `water_pending_charges` row is recorded and the next invoice folds it into water_due (non-destructive) |
| Arrears view | `WaterArrearsService` (Overdue/Partial invoices with water_due>0 + outstanding) — panel on the Meters page; water isn't payment-separable from rent (payments hit the invoice total) |
| Reminder | `water:arrears-notify` (daily 08:05) warns tenants with an overdue water bill (`water_arrears` IMPORTANT, idempotent per invoice+month) |
| Tenant | Tenant/Water.vue shows a "service disconnected — pay to reconnect" banner |

### Operator notes
- Disconnection is a per-unit lever; for a common/shared meter (flat-rate / borehole main) it is intentionally unavailable — enforce off-system or sub-meter the units.
- The reconnection fee bills on the unit's NEXT invoice (only if the unit has an active lease); a vacant unit reconnects with no charge.

## Phase 91 — water intelligence (landlord)

The landlord water hub gains an **Intelligence** tab (landlord-only — caretakers
never see it; production costs/margin are business data). All metrics come from
`WaterIntelligenceService::forLandlord()` — batched grouped queries, no N+1, every
ratio guards a zero denominator. Two windows: a **12-month** monthly trend, and a
trailing **3-month** window for the "current state" metrics.

| Metric | Meaning / source |
| --- | --- |
| Consumption trend | approved-reading consumption summed per month (12 buckets, gaps filled with 0) |
| Month change | last full month vs the prior month (delta %); `null` when the prior month is 0 |
| Projection | trailing-3-month average — a simple next-month estimate |
| By building / top consumers | approved consumption grouped by building / by unit (last 3 months) |
| Leak signals | recent `is_anomalous` readings (Phase-86 spike flag) — likely leaks to verify |
| Non-revenue water | where a **main meter feeds sub-meters**, main − Σ(sub) = water paid for but unbilled (leak / unmetered draw); empty when no main/sub hierarchy exists |
| Billing vs collection | water billed (Σ `invoices.water_due`) vs the **water share** of payments, approximated pro-rata `amount_paid × water_due/total_due` (payments are not water-allocated) + collection rate + outstanding |
| Cost of production vs revenue | margin = water billed − logged production costs; `water_production_costs` log (pump electricity / maintenance / permit / other), per landlord, optionally per building; + cost-per-unit-produced |

| Concept | Where |
| --- | --- |
| Service | `App\Services\Water\WaterIntelligenceService` |
| Tab | `WaterHubController::getIntelligenceData` (caretaker → bounced to overview) + `resources/js/Pages/Water/tabs/IntelligenceTab.vue` (reuses `Components/Dashboard/ChartCard.vue`) |
| Production cost | `water_production_costs` table + `WaterProductionCost` model/policy/factory; `WaterProductionCostController` store/destroy (landlord-only, `water.production-costs.*`) |

### Operator notes
- **Margin is only as good as the costs you log.** Borehole landlords should add pump electricity + maintenance + permit costs on the Intelligence tab; without them margin == revenue.
- Collection rate is an **estimate** — water can't be split from rent at payment time, so the water share of each payment is pro-rated by its share of the invoice total.
- Non-revenue water only appears once a building is sub-metered (a main meter with `parent_meter_id` children). For unmetered/flat-rate buildings it is intentionally absent.
- County-supply buildings (`water_source = county`) have no borehole production cost — log the county bill as a `maintenance`/`other` cost if you want it in the margin.

## Phase 92 — water compliance (landlord)

A landlord-only **Compliance** tab on the water hub, scoped to **borehole**
buildings (effective `water_source = borehole`, building override else the global
config). It tracks the WRA abstraction permit + water-quality certificate and the
annual abstraction limit vs actual abstraction.

| Concept | Where |
| --- | --- |
| Service | `App\Services\Water\WaterComplianceService::forLandlord` |
| Tab | `WaterHubController::getComplianceData` (caretaker → bounced to overview) + `resources/js/Pages/Water/tabs/ComplianceTab.vue` |
| Permit / cert files | **Documents** (`documentable_type = Building`, types `wra_abstraction_permit` / `water_quality_certificate`) — uploaded via the shared `documents.store`, renewed via `documents.renew` |
| Expiry reminders | **REUSED** from Phase-82: a renewable building doc with `expires_at` is found by `documents:scan-expiring` → `document_expiry` notification to the landlord (no new cron, no new notification type) |
| Abstraction limit | `buildings.water_abstraction_limit` (m³/year) set via `water.compliance.limit` (landlord-only) |
| Abstraction used | calendar-year approved consumption — prefers the building's **main meter** (a top-level meter with sub-meters = the abstraction point); else summed unit consumption (`basis` tells which) |
| Status | `no_limit` / `unknown` (no readings) / `ok` / `warning` (≥90% or projected to exceed) / `exceeded`; honest nulls — never a fabricated "compliant" |

### Operator notes
- The Compliance tab only lists **borehole** buildings. Set a building's water source to Borehole in Settings to track it; county/municipal buildings are intentionally absent.
- Permit/cert renewal reminders are the **same** machinery as every other document expiry — set the doc renewable with an expiry + reminder days and the daily scan handles it.
- Abstraction "used" is metered consumption. Where a building has a borehole **main meter** (a metered parent feeding sub-meters), that reading is the true abstraction total; otherwise it is estimated from unit meters and **understates** real abstraction (leaks/common areas not metered) — sub-meter the building for an accurate compliance figure.
- A single borehole supplying multiple buildings: model the limit per building (split it, or set it on the building carrying the main meter). A first-class shared-source model arrives with the water-clients epic (Phase 94+).

## Phase 93 — tenant water self-service

The tenant water view (`/water`, `tenant.water`, water.module gated) is now a self-service dashboard, not a flat readings table.

| Concept | Where |
| --- | --- |
| Service | `App\Services\Water\WaterAccountService` — **unit-centric** (charges by lease), so the Phase-94+ water-client dashboard reuses it verbatim |
| Data | `overview(unitId, ?leaseId)` → 12-month consumption history + summary (latest / monthly average / year-to-date) + leak self-alert + per-period water-charge history |
| Surface | `TenantPortalController::water` + `resources/js/Pages/Tenant/Water.vue` |
| Shared components | `resources/js/Components/Water/` — `WaterDisconnectionBanner` (payUrl prop), `WaterUsageAlert`, `WaterConsumptionCard` (reuses ChartCard — the first chart on the tenant side), `WaterChargesCard`. **Pure presentational, data-only props** → reused as-is by the water-client dashboard. |
| Leak self-alert | surfaces the tenant's latest reading's Phase-86 `is_anomalous` spike flag as a non-alarming advisory (check for leaks / running taps) — only when actually flagged |

### Operator notes
- The tenant sees only **approved** readings (pending/rejected never appear), consistent with the rest of the tenant portal.
- The leak self-alert is the same `is_anomalous` flag the landlord reviews — no separate detection. It reflects the LATEST reading only.
- Charges show water_due per billing period with a settled/outstanding chip; "settled" means the whole invoice is paid (water isn't separable from rent at payment time).
- The four `Components/Water/*` cards are the deliberate reuse seam for the water-clients epic — a water-client dashboard (Phase 96) composes the same components with a different pay route and no rent context.
- Readings are scoped to the tenant's **occupancy window** (lease `start_date` onward) — `water_readings` has no `lease_id`, so a tenant on a previously-occupied unit must not see the prior occupant's history. The water-client reuse passes its service-start date the same way.
- Known limitation: **imported** historical readings (Phase-89) are not spike-flagged (`is_anomalous` defaults false, the import bypasses the spike check), so the leak self-alert reflects normally-recorded readings, not bulk-imported history.

## Phase 94 — water clients (foundation)

A landlord can supply water to **non-tenant clients** (e.g. a borehole feeding neighbours) billed at a different rate. Phase 94 is the foundation (model + role + landlord setup/management); onboarding, dashboard, and billing land in Phases 95–97.

| Concept | Where |
| --- | --- |
| Identity | A water supply is a **relationship** (`WaterConnection` = the "water line", the analogue of a Lease), NOT a role. A water-only person is a `User` with the new single-value role `water_client` (added Phase 94; users created at onboarding, Phase 95). Dashboards render by capability (`has_water_connection`). |
| Model | `App\Models\WaterConnection` (`water_connections`) — landlord_id, user_id (the client account, null until onboarded), unit_id/meter_id (the metering point, both nullable — a client line can be unit-less), identifier (landlord-defined code), client_name, billing_mode (metered/flat_rate), client_rate, status, connected_at. TenantScope + SoftDeletes. |
| Opt-in | `payment_configurations.supplies_water_clients` + `water_client_rate` (the default different rate). Managed on the clients tab, not the shared water settings form. |
| Surface | landlord-only **Clients** tab (`WaterHubController::getClientsData`, caretaker-bounced) → `Pages/Water/tabs/ClientsTab.vue`: a setup wizard (declare supply + default rate) until opted in, then water-line management (create/edit/delete connections). `WaterConnectionController` setup + store/update/destroy (`water.clients.setup`, `water.connections.*`). |

### Operator notes
- A water line (connection) can exist **before** the client has an account — the landlord identifies + rates the line now; the `water_client` user is invited/onboarded in Phase 95 and linked via `user_id`.
- The client rate lives on the connection (overriding the default `water_client_rate`); the Phase-97 biller will charge water clients at this rate via the Phase-87 tariff engine.
- `water_client` is scoped to the supplier landlord (`TenantScope`, keyed on `landlord_id`), exactly like a tenant.
- Deferred to 95–97: client invitation/onboarding + required docs + payment method (95), the water-client dashboard reusing the Phase-93 `Components/Water/*` (96), and invoice/statement/payment billing (97).
