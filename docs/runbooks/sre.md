# Phase-32 SRE Runbook

The runbook *about* runbooks. Tier-5 operational excellence — alert
registry, error budgets, operational incidents, dependency health.

## Alert registry

Every alert that pages or emails the on-call lives in
`config/alerts.php` as an array entry. Required fields: `key`,
`severity` (sev1-4), `threshold`, `window`, `gauge`, `runbook`,
`paging` (email|page|both), `description`.

To add a new alert:
1. Pick a snake_case `key` no other alert uses.
2. Add the entry to `config/alerts.php`.
3. Point `runbook` at an existing markdown file + heading anchor.
4. Wire the source code to call `AlertFiringRecorder::record($key, $value, ...)`
   when the threshold is crossed and `resolve($key)` when recovered.
5. Run `php artisan runbook:coverage-audit` to verify the runbook
   reference resolves; commit. The Phase32SreSurfaceTest watchdog
   asserts the cron is scheduled.

## Error budgets

`service_slos` table holds per-tier objectives (loaded by
Phase32ServiceSloSeeder from `slo.md`). `ErrorBudgetCalculator::compute`
returns budget_remaining_pct + burn_rate_1h + burn_rate_6h using
AlertFiring duration on the bad-indicator metric as the SLI proxy.

`slo:budget-audit` runs every 15 minutes. Multi-window burn-rate alert
fires when both:
- `burn_rate_1h > 14.4x` (single-window fast-burn)
- `burn_rate_6h > 6.0x` (sustained burn)

The conjunction filters the single-window false-positive rate that
plagues 1h-only alerting. When the alert fires, the on-call's first
action is to consider HALTING DEPLOYS until the burn rate stabilises.

## Operational incidents

`OperationalIncident` is distinct from `SecurityIncident` (Phase-13):
- **SecurityIncident** = ATTACKS (failed logins, signature floods, large exports) — legal SLA.
- **OperationalIncident** = OUTAGES + DEGRADATIONS (Daraja down, queue wedge) — best-effort SLA.

Severities:
- **SEV1** : full service down for paying users — page immediately, all-hands.
- **SEV2** : major feature degraded OR < 25% users affected — page on-call.
- **SEV3** : minor feature degraded OR < 5% users affected — email on-call.
- **SEV4** : informational — log only.

Status machine: open → investigating → mitigated → resolved (forward only).

Open via `POST /ops/incidents` (super_admin only). Always link the
post-mortem URL within 5 business days using the template at
`docs/runbooks/post-mortem-template.md`. MTTR is the canonical
leadership metric — `mttr:audit` weekly emits p50/p90 per severity.

## Dependency health

`DependencyHealthService::check(dep)` returns up|degraded|down per
upstream (daraja, paystack, intasend, smtp, sms, redis). The
`outbound:health-check` cron runs every 5 minutes and emits
`dependency_up{dep=X}` (1.0/0.5/0.0) + `dependency_latency_ms{dep=X}`.

On transition (e.g. `up → down`), `DegradationDetected` event fires;
`LogDegradationDetected` listener writes to `workflow_runs_log` so
the Phase-29 `workflow:health` silent-failure dashboard surfaces it.

If a dep is down, `dependency_down` alert fires. Resolution: see the
provider's status page; if confirmed outage, open a SEV2 incident +
notify customers; if no provider-side outage, escalate to dev for
network/credential investigation.

## Crons

| Command | Cadence | What it does |
|---|---|---|
| `runbook:coverage-audit` | weekly Sun 06:00 | Validate alert→runbook refs |
| `runbook:staleness-audit` | weekly Sun 06:30 | Bucket runbooks by age |
| `alert:quality` | daily 06:00 | Per-alert signal-to-noise ratio |
| `slo:budget-audit` | every 15 min | Per-service burn rate + alert |
| `mttr:audit` | weekly Mon 06:45 | Per-severity MTTR p50/p90 |
| `outbound:health-check` | every 5 min | Probe + emit per-dep up gauge |

## CI gates

- `Phase32RunbookCoverageTest` — alert registry shape + every ref resolves
- `Phase32AlertFiringTest` — recorder idempotency + quality scoring
- `Phase32ErrorBudgetTest` — calculator math + multi-window burn alert
- `Phase32IncidentTest` — status machine + post-mortem URL + MTTR audit
- `Phase32DependencyHealthTest` — service shape + outbound cron + transitions
- `Phase32SreSurfaceTest` — 6 crons + 2 events + sre.php lang parity

## Deferrals

None for Phase 32. Sub-scope (NOT PRD findings) deferrals: PagerDuty/OpsGenie
HTTP integration for the `slo_budget_fast_burn` alert (today emits-and-emails
only), Sre/Incidents/Index.vue and Sre/Alerts/Index.vue dashboards (data +
API shipped, dedicated Vue pages land in a follow-up).
