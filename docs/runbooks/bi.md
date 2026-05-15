# Reporting + BI operator runbook

Phase-27 [REPORTING + BI] adds cohort analytics, NOI/cap-rate,
rent-roll forecasting, a custom report builder, and scheduled xlsx
delivery. This runbook documents methodology choices, query
allowlists, and operator workflows.

## Cohort analytics

### Retention matrix (BI-COHORT-1)

`CohortService::retentionMatrix(landlordId, lookbackMonths)` returns a
triangular survival matrix:

```
matrix[cohortMonth][offset] = float (0.0 — 1.0)
```

A cohort is the set of leases that **started** in a given YYYY-MM. The
offset is months from that start. `matrix[2026-01][0] = 1.0` by
definition (every cohort survives its own start month). Future offsets
are `null`.

A lease counts as alive on observation date `D` when:

```
start_date <= D AND (end_date IS NULL OR end_date >= D)
```

`is_active=false` leases with an `end_date` correctly drop out at the
right offset. Soft-deleted leases (TenantScope + SoftDeletes) are
excluded from the matrix automatically.

### Acquisition table (BI-COHORT-2)

`CohortService::acquisitionTable(landlordId, months)` returns one row
per month:

| Column        | Definition |
|---------------|-----------|
| `new`         | Leases started in the month, where the tenant has no earlier lease with this landlord |
| `reactivated` | Leases started in the month, where the tenant DID have an earlier lease |
| `churned`     | Leases with `end_date` falling in the month |
| `net_delta`   | `new + reactivated − churned` |

**Identity contract**: `Σ net_delta` over the window equals
`active_at_end − active_at_start`. The `Phase27CohortTest::test_acquisition_table_balances` watchdog enforces it.

### Lifetime value (BI-COHORT-3)

LTV is a methodology choice, not a formula. PropManager's choice:

> **LTV = sum of non-voided payments from tenants in the cohort,
> aggregated since the lease started. Refunds are NOT subtracted (gross
> LTV).**

Reasoning:

1. **Non-voided** — voided payments represent reversed transactions
   (chargebacks, fraud, recording errors). Including them would
   over-state LTV.
2. **Refunds not subtracted** — refunds live in a separate `Refund`
   model (Phase-25 RefundPolicy). The current implementation does NOT
   join through them. This is a documented limitation that a future
   Phase-N finding can close; the data is correct for the gross-of-
   refunds interpretation today.
3. **Lease renewal** — if a tenant's first lease ends and they sign a
   new one with the same landlord, payments under the SECOND lease
   still count toward the FIRST lease's cohort (the cohort identifies
   the tenant's acquisition, not the lease).
4. **Subletting** — out of scope. Subletting is rare in the Kenyan
   market this product serves; addressing it would be a Phase-N
   addition with a `lease.parent_lease_id` schema change.

Mean and median are computed across the cohort's TOTAL tenant set —
tenants who haven't yet made a payment count toward the divisor with
LTV=0. This depresses mean LTV early in a cohort's life (honest
representation of "we don't know yet").

## NOI + cap rate

### NOI per property (BI-NOI-1)

`NoiService::byProperty(landlordId, start, end)` returns one row per
property plus a portfolio total:

```
{ property_id, name, revenue, direct_expenses, allocated_expenses, noi, noi_margin }
```

- **revenue** — sum of non-voided payments whose lease's unit's
  building belongs to this property, within the window.
- **direct_expenses** — expenses with `allocation_method='direct'`
  attached to this property (directly via `expenses.property_id`,
  or indirectly via `expenses.building_id`/`expenses.unit_id`).
- **allocated_expenses** — pro-rata share of non-direct expenses
  (see BI-NOI-3 below).
- **noi** — revenue − direct_expenses − allocated_expenses.
- **noi_margin** — noi ÷ revenue, or null when revenue is zero.

Portfolio total is the sum across all properties — including
allocated expenses, so the portfolio NOI equals
`Σ revenue − Σ all_expenses` regardless of how allocation distributes
them across properties.

### Cap rate (BI-NOI-2)

`NoiService::capRate(landlordId, start, end)` returns:

```
{ property_id, name, annualised_noi, estimated_value, cap_rate, band }
```

- **annualised_noi** — period NOI scaled to a full year by
  `365 / period_days`. A 3-month NOI multiplies by ~4.
- **estimated_value** — `properties.estimated_value`. Nullable;
  cap_rate is null when absent (UI shows N/A).
- **cap_rate** — annualised_noi ÷ estimated_value as a decimal
  (0.075 = 7.5%).
- **band** — Kenyan residential market convention:
  - `< 6%` → amber (underperforming or overvalued)
  - `6%-9%` → green (typical residential)
  - `> 9%` → emerald (commercial-tier yield)
  - null → unknown (gray, no value declared)

The annualisation handles short reporting windows correctly. A
landlord viewing the 1-month NOI gets a 1-month NOI value in the
NOI table AND an annualised cap rate that's directly comparable to
T-bill yield or REIT dividends.

### Expense allocation methodology (BI-NOI-3)

`expenses.allocation_method` is an enum-like string column with four
values:

| Method            | Formula                                       | When to use |
|-------------------|-----------------------------------------------|-------------|
| `direct`          | Attribute to `property_id` field directly     | Property-specific expense (repair on building X) |
| `per_unit`        | `expense × (property_units / total_units)`    | Operating expense not tied to a property (insurance) |
| `per_revenue`     | `expense × (property_revenue / total_revenue)`| Revenue-driven expense (payment-processor fees) |
| `per_floor_area`  | _Currently aliased to `per_unit`_             | Future: needs `units.floor_area_m2` column |

The `per_floor_area` method is reserved for a future migration that
adds floor area to units. Today it falls back to `per_unit` so
operators can already pick it and revisit when the data lands.

**Why pre-Phase-27 NOI was wrong**: every general expense (accountant
fee, software, marketing) had `property_id=NULL` and got attributed
only at the portfolio level. Per-property NOI looked artificially
high. Now operators pick an allocation method per expense; the UI
on `Expenses/Edit.vue` exposes the picker (Phase-N follow-up wires
the UI).

**Adding a new method**: append to `Expense::ALLOCATION_METHODS`,
extend the switch in `NoiService::allocatedExpensesPerProperty()`,
add a row to the table above. The constant + the switch + the doc
table is a three-point contract — drift between them is caught by
`Phase27NoiTest::test_every_allocation_method_is_documented`.

## Forecasting

### Rent-roll forecast (BI-FORECAST-1)

`ForecastService::rentRoll(landlordId, monthsAhead)` projects expected
revenue 1-24 months out. For each month:

| Column            | Formula                                                |
|-------------------|--------------------------------------------------------|
| `active_rent`     | Σ rent_amount for leases active that month             |
| `low_estimate`    | `active_rent × collection_rate` (conservative)         |
| `expected_revenue`| `low_estimate × seasonality_factor`                    |
| `high_estimate`   | `active_rent + (vacant_units × avg_rent × fill_rate)`  |

Lease lifecycle: a lease counts as active in month M when its
`start_date <= M.last_day AND (end_date IS NULL OR end_date >= M.first_day)`.
Mid-window endings drop off at their end-date month — no automatic
renewal assumed.

`collection_rate` is `Σ payments / Σ expected_rent` over the last
12 months, clamped to `[0.5, 1.0]`. Falls back to 0.85 when there
isn't enough history. The clamp prevents outliers from a fresh
portfolio (where `Σ expected_rent` is small and noisy) from
producing absurd rates.

`vacancy_fill_rate` is `mean_leases_per_unit_per_year / 12` — the
fraction of vacant units filled in any given month. Clamped to
`[0.1, 0.9]` with a 0.4 default.

### Seasonality (BI-FORECAST-2)

`ForecastService::seasonalityFactor(landlordId, month)` computes the
landlord's own seasonal multiplier from the last 3 years of payment
history:

```
factor = mean(monthly_total) for the given month / mean(monthly_total) across all months
```

Returns 1.0 when there's <12 months of data — a thin sample produces
worse forecasts than the naive flat assumption. Kenya-wide
seasonality (Dec early-payment surge, Jan vacancy bump) emerges
naturally from each landlord's own history without hardcoded
overrides.

### Vacancy projection (BI-FORECAST-3)

`ForecastService::vacancyProjection(landlordId)` returns one row per
vacant unit:

| Column                | Source |
|-----------------------|--------|
| `unit_id`             | `units.id` |
| `unit_number`         | `units.unit_number` |
| `vacant_since`        | Last lease's `end_date` (null if never leased) |
| `expected_fill_date`  | `today + mean_time_to_fill_days` |
| `lost_revenue_kes`    | `(target_rent / 30) × days_to_fill` |

`mean_time_to_fill_days` is computed from the landlord's history: for
every lease in the last 12 months, find the prior lease on the same
unit and take `DATEDIFF(current.start_date, prior.end_date)`. Clamped
to `[7, 180]` days with a 45-day default when the landlord has no
back-to-back lease history.

Rows are sorted by `lost_revenue_kes DESC` so the highest-impact
vacancies surface first.

## Custom report builder

### Saved-report library (BI-BUILDER-1)

`saved_reports` table persists `(landlord_id FK CASCADE, name,
description, config JSON, timestamps)`. The `SavedReport` model uses
`TenantScope` for read-side isolation; the FK cascade is the
write-side cleanup. `SavedReportPolicy` requires:
- viewAny: landlord or caretaker
- view / update / delete: landlord whose id matches the row's
  `landlord_id`
- create: landlord (super-admin bypass + DPA-4 restriction apply
  through `Gate::before` as usual)

`config` JSON is the picker output, validated at write time by
`ReportBuilderService::run()` — even after persistence we revalidate
on every execution. The DB layer is NOT the trust boundary.

### Drag-drop field picker UI (BI-BUILDER-2)

`Pages/Reports/Builder.vue` reads `allowedTables` + `allowedFields` +
`operatorMatrix` from the server (the BuilderController emits them
from `ReportBuilderService::ALLOWED_*` constants). The UI cannot
synthesise an unsafe field value even if a malicious actor edits
the DOM — every picker option originates server-side.

Today's UI is click-to-add (field → select → filter row → preview).
Full drag-drop polish is a Phase-N follow-up; the safety contract is
the picker output, not the interaction model.

### Safe SQL generator (BI-BUILDER-3)

`ReportBuilderService::run(config, landlordId)` is the
**security-critical** code path. Two defence-in-depth rules govern
the file:

1. **Allowlist validation at the boundary**. Every field, table,
   operator, sort direction, and group-by reference is checked
   against an in-file constant (`ALLOWED_TABLES`, `ALLOWED_FIELDS`,
   `NUMERIC_OPERATORS`, `DATE_OPERATORS`, `STRING_OPERATORS`,
   `BOOLEAN_OPERATORS`, `SORT_DIRECTIONS`). Anything not on the
   list throws `ValidationException` before the query is built.
2. **Parameterised queries only**. Every user-supplied value flows
   through Eloquent's `->where()` / `->whereIn()` / `->orderBy()`
   bindings. The service NEVER calls `DB::raw()` with any value
   that originates from the request.

`Phase27BuilderInjectionTest` is the gate: 20+ classic SQL-injection
payloads fired at every input slot (table, fields, filters.field,
filters.op, filters.value, group_by, sort_by.field, sort_by.direction,
limit). Every payload must reject. If any assertion fails, the
builder is INSECURE — fix validation, don't loosen the test.

### Field allowlist

| Key                        | Table     | Column           | Type    |
|----------------------------|-----------|------------------|---------|
| `payment.amount`           | payments  | amount           | numeric |
| `payment.payment_date`     | payments  | payment_date     | date    |
| `payment.payment_method`   | payments  | payment_method   | string  |
| `invoice.total_due`        | invoices  | total_due        | numeric |
| `invoice.amount_paid`      | invoices  | amount_paid      | numeric |
| `invoice.status`           | invoices  | status           | string  |
| `invoice.due_date`         | invoices  | due_date         | date    |
| `lease.rent_amount`        | leases    | rent_amount      | numeric |
| `lease.start_date`         | leases    | start_date       | date    |
| `lease.is_active`          | leases    | is_active        | boolean |

**Adding a field**: edit `ReportBuilderService::ALLOWED_FIELDS`,
update this table, run `Phase27BuilderInjectionTest`. The
`test_field_allowlist_is_locked` assertion adapts automatically.

### Allowed cross-table joins

| Root table | Joinable to |
|------------|-------------|
| payments   | leases, invoices |
| invoices   | leases       |

Anything outside this table — e.g. payments → users — is forbidden.
Field references that would require such a join throw
`ValidationException` at the entrance.

## Scheduled delivery

_Filled in by Phase 2 — BI-DELIVERY-1/2/3._

## CI gates

_Filled in by Phase 2 — BI-CI-1/2/3._

## Related runbooks

- `docs/runbooks/api-deprecation.md` — Phase-25 API versioning + Sunset contract
- `docs/runbooks/pwa.md` — Phase-26 PWA shell + offline + caching
- `docs/runbooks/i18n.md` — Phase-24 two-engine localisation
- `docs/runbooks/accessibility.md` — Phase-23 a11y baseline
- `docs/runbooks/policy-and-index.md` — Phase-19 Policy + DB-index conventions
