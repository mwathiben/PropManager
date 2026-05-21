# Reports — operations runbook

Owner: Reports / BI workstream.
Last touched: Phase 50 — REPORTS-DEPTH.

This runbook covers the landlord-facing reports system: the SAFE SQL
builder (Phase 27), the four Phase 50 capability deepenings (drill-down,
template marketplace, custom metrics, real-time preview, landlord
dashboards), and the diagnostic + remediation playbooks the on-call
engineer needs when a report render fails.

For deeper Phase 27 architecture see `bi.md`.

---

## 1. Surface map

| Capability             | Phase   | Entrypoint                                        | Storage                          |
|------------------------|---------|---------------------------------------------------|----------------------------------|
| Custom report builder  | 27      | `GET /reports/builder`                            | `saved_reports`                  |
| Scheduled delivery     | 27      | `GET /reports/scheduled`                          | `scheduled_reports`              |
| Drill-down child       | 50 1a   | `GET /reports/builder/{report}/drill?segment=...` | `saved_reports.parent_report_id` |
| Template marketplace   | 50 1b   | `GET /reports/templates`                          | `report_templates`               |
| Custom metrics         | 50 1c   | `GET/POST /reports/metrics`                       | `report_metrics`                 |
| Preview next send      | 50 1d   | `POST /reports/scheduled/preview`                 | (computed, no storage)           |
| Landlord dashboards    | 50 1e   | `GET /dashboards/{slug}`                          | `landlord_dashboards`            |

All routes are `role:landlord`-gated.

---

## 2. Security contracts (do NOT regress)

### 2a. Builder SQL allowlist

The custom builder is the highest-risk surface in this whole subsystem.
`ReportBuilderService::ALLOWED_TABLES` + `ALLOWED_FIELDS` is the
defence. Every field, table, operator, and group-by reference is
validated against an in-file allowlist; user input never reaches
`DB::raw()`. `Phase27BuilderInjectionTest` is the gate.

### 2b. Metric formula DSL

`MetricFormulaService` parses landlord-supplied formula strings into
RPN at write time. The contract is documented at the top of the file
and asserted by `Phase50ReportsDepthSurfaceTest::test_metric_formula_service_rejects_classic_injection_payloads`:

- NO `eval()`, NO `preg_replace_callback` /e, NO dynamic dispatch.
- Tokens restricted to: numbers, field references `{table.col}` whose
  inner key is in `ALLOWED_FIELDS`, `(`, `)`, and the five arithmetic
  operators.
- Expression bounded at 1024 source chars, 128 tokens, 64 paren depth,
  20-char number literals.

### 2c. Cross-tenant guards

| Service                                | Guard                                                 |
|----------------------------------------|-------------------------------------------------------|
| `ScheduledController::preview`         | `saved_report.landlord_id` matches caller — else 403  |
| `DashboardService::renderCard`         | `saved_report.landlord_id` matches dashboard owner    |
| `DashboardService::renderCard` (metric)| `report_metric.landlord_id` matches dashboard owner   |
| `DrillDownService::resolveChild`       | re-validates `drill_field` against `ALLOWED_FIELDS`   |
| `ReportTemplateService::cloneFor`      | caller role must be `landlord`; new SavedReport carries caller's id |

---

## 3. Daily checks (manual on-call)

```bash
# count failed render attempts in the last hour
php artisan tinker --execute='
    echo "saved_reports: " . App\Models\SavedReport::query()->count();
    echo "scheduled_reports due now: " . App\Models\ScheduledReport::query()
        ->where("next_due_at", "<=", now())->count();
    echo "active templates: " . App\Models\ReportTemplate::active()->count();
    echo "active metrics: " . App\Models\ReportMetric::query()->where("is_active", true)->count();
'

# Confirm scheduled-report cron is running
grep "reports:dispatch-scheduled" storage/logs/laravel.log | tail -10
```

If a landlord reports "my dashboard 500s", the most common cause is a
stale layout JSON referencing a saved-report that was deleted. The
`DashboardService::renderCard` validator throws `ValidationException`
with a card-index-precise message — read the log line first, don't
guess.

---

## 4. Incident playbooks

### 4a. "Builder run throws ValidationException"

This is by design. The landlord submitted a field / op / table that
isn't in the allowlist. Steps:

1. Read the validation message — it tells you exactly which input
   failed validation.
2. If the rejection is correct (user picked an invalid combination),
   close the ticket as "working as designed".
3. If the user is requesting a NEW field, extending the allowlist is
   the change you want — follow the protocol at the top of
   `ReportBuilderService.php`.

### 4b. "Metric formula evaluates to inf / NaN"

A landlord-supplied formula divided by zero (or evaluates a row whose
denominator is null/zero). `MetricFormulaService::evaluate` returns
`0.0` on division by zero rather than `inf` — but if you see `NaN` in
output, the row data itself contains `NaN`, which means upstream data
ingest is broken. Investigate the source row first.

### 4c. "Drill-down 400s with `drill_field is null`"

The parent SavedReport never had `drill_field` set. Two fixes:

1. Have the landlord edit the parent in the Builder and pick a drill
   field (a string-typed ALLOWED_FIELDS key).
2. Or run:
   ```sql
   UPDATE saved_reports SET drill_field = 'payment.payment_method'
   WHERE id = X AND landlord_id = Y;
   ```
   (Always include `landlord_id` — this runbook never touches multi-tenant
   tables without it.)

### 4d. "Preview returns 0 rows but landlord expects data"

Three causes, in likelihood order:

1. The saved-report's filters exclude every row. Open the Builder, run
   the same config without filters, compare counts.
2. The saved-report references a payment method / status that the
   landlord renamed (e.g. moved from `mpesa` to `m_pesa`).
3. The landlord is viewing a child drill-down whose segment value is
   stale. The drill-route synthesises a child config at request time
   with the segment as a filter — if no row matches, you get 0.

### 4e. "Dashboard show page 500s"

`DashboardService::buildPayload` fails closed on bad layout JSON. The
exception message names the offending card index. Likely causes:

1. The `saved_report_id` in the layout refers to a row that the
   landlord soft-deleted.
2. The `metric_slug` refers to a metric the landlord deactivated.
3. The layout was hand-edited via direct DB and contains an unknown
   `type` value.

Re-create the dashboard via the builder or surgically fix the layout
JSON; do not loosen the validator.

### 4f. "Template clone produces an empty SavedReport"

`ReportTemplateService::cloneFor` deep-copies the template's `config`
JSON. If the resulting SavedReport runs to zero rows, that's expected
behavior for a freshly cloned report — the landlord must have data
matching the template's default filters. Educate, don't escalate.

---

## 5. Field-allowlist extension protocol

If a landlord asks for a new column in the builder / a new metric
field / a new template column, the protocol is:

1. Add the field to `ReportBuilderService::ALLOWED_FIELDS` with its
   `(table, column, type, label)` tuple.
2. Add the table to `ALLOWED_TABLES` if new.
3. Add the join shape to `JOINS` if it crosses tables.
4. Run `Phase27BuilderInjectionTest` — its `test_field_allowlist_is_locked`
   covers the new field automatically.
5. Update the "Field allowlist" section in `bi.md`.

The same workflow applies for metric fields (which currently piggy-back
on the builder allowlist via `MetricFormulaService::assertFieldRefsInAllowlist`).

---

## 6. Alert thresholds

See `alert-thresholds.md` for the full list. Reports-related rows:

- `report_render_failure_count` (sev3) — fires when ScheduledController
  or BuilderController returns a 5xx more than 3 times in 15 minutes.

When this alert fires, look at `storage/logs/laravel.log` for stack
traces beginning with `ReportBuilderService::run` or
`DashboardService::buildPayload`. If the cause is a single landlord's
malformed config, page the on-call engineer; if it's a broad
infrastructure issue (DB unreachable, queue worker dead), escalate to
SRE.

---

## 7. REPORTS-DEPTH-2 (Phase 73)

The report-tool suite is reachable from **Finances → Reports → Report
tools** (the launcher card in `ReportsTab.vue`): Builder, Dashboards,
Templates, Scheduled, Shared Links, Custom Metrics.

### Landlord dashboards (editor)

- `dashboards.index` lists; `dashboards.create`/`store`/`edit`/`update`
  drive the card editor (`Pages/Dashboards/Editor.vue`); `dashboards.show`
  renders read-only.
- `StoreDashboardRequest` + `DashboardService::validateLayout` re-validate
  EVERY card's `saved_report_id`/`metric_slug` for landlord ownership on
  write — the layout JSON is never trusted. `dashboards.preview` runs the
  transient layout; a cross-tenant card id 422s.

### Report shares (signed, public)

- Landlord mints a time-boxed link from `reports.shares.index`. The public
  `reports.share.view` route is `signed`-gated (no auth) and runs the report
  with the share row's OWN `landlord_id` — never a request param. Swapping
  the share id breaks the signature. `reports.shares.revoke` is idempotent.
- A saved report whose config drifted out of the allow-list degrades to an
  "unavailable" state on the public view rather than leaking a 500.

### Scheduled report edit + pause

- `reports.scheduled.update` edits cadence + recipient (recipient stays in
  the Phase-13 allow-list). Changing the cadence re-anchors `next_due_at`
  from now(); a recipient-only edit does not slide the next send.
- `reports.scheduled.toggle-pause` pauses (sets `paused_at`) / resumes
  (clears it + re-anchors `next_due_at`). The `reports:send-scheduled` cron
  skips rows with a non-null `paused_at` (`whereNull('paused_at')`), so a
  paused stretch never fires a backlog.

### Custom metrics (editor + live validation)

- `reports.metrics.manage` is the author page (`Pages/Reports/Metrics.vue`).
  `reports.metrics.validate` parses an expression live via
  `MetricFormulaService` (no persist) so a bad/injection formula surfaces
  its message before save.
- New safe dimensions were added to `ReportBuilderService::ALLOWED_FIELDS`
  (e.g. `invoice.arrears`, `lease.deposit_amount`). To add more, follow
  section 5 — group-by stays validated against the allow-list and values
  stay parameterised (NO `DB::raw`). Numeric entries auto-become
  metric-eligible.
