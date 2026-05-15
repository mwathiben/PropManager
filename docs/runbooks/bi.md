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

_Filled in by Phase 1c — BI-FORECAST-1/2/3._

## Custom report builder

_Filled in by Phase 1d — BI-BUILDER-1/2/3._

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
