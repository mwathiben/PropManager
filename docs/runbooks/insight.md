# Insight runbook — Phase 36

## Overview

Phase 36 surfaces 35 phases of accumulated telemetry (MRR, churn,
engagement, referrals, usage, subscription changes, product events,
SLOs, incidents, alerts) on three planes:

1. **Operator** — `/ops/insight` dashboard + `/ops/cost`, `/ops/mrr`
   drill-downs (super_admin only).
2. **Landlord** — Dashboard growth widget cluster + `/growth`
   subpage; self-serve `/api/v1/landlord/*` endpoints.
3. **Cron budget** — `cron:budget-audit` + `high_cron_runtime` alert
   keep the nightly growth cluster from exceeding 60 minutes of
   wall-clock time.

## Surface map

| Surface | Path | Audience | Auth |
| --- | --- | --- | --- |
| Ops dashboard | `/ops/insight` | super_admin | session |
| Landlord cost drill-down | `/ops/cost` | super_admin | session |
| MRR trend drill-down | `/ops/mrr` | super_admin | session |
| Landlord growth subpage | `/growth` | landlord | session |
| Engagement API | `GET /api/v1/landlord/engagement` | landlord | sanctum `landlord:manage` |
| Engagement CSV export | `GET /api/v1/landlord/engagement/export.csv` | landlord | sanctum `landlord:manage` |
| Usage API | `GET /api/v1/landlord/usage` | landlord | sanctum `landlord:manage` |
| Referrals API | `GET /api/v1/landlord/referrals` | landlord | sanctum `landlord:manage` |
| Insight summary | `GET /api/v1/landlord/insights/summary` | landlord | sanctum `landlord:manage` |
| Product events xlsx | `GET /api/v1/landlord/product-events/export` | landlord | sanctum `landlord:manage` |
| MRR snapshot xlsx | `GET /ops/exports/mrr.xlsx` | super_admin | session |

## Operator dashboard KPI sources

| KPI | Source | Window |
| --- | --- | --- |
| `mrr_today` | `MrrSnapshotService::current()` | latest `mrr_snapshots` row |
| `delta_30d` | `MrrSnapshotService::deltaOverDays(30)` | trailing 30d |
| `monthly_churn_rate` | `ChurnAuditCommand` last gauge | trailing 30d |
| `active_incident_count` | `OperationalIncident::active()` | open at query time |
| `last_24h_alert_count` | `AlertFiring::firedSince(24h)` | trailing 24h |
| `unresolved_alert_count` | `AlertFiring::unresolved()` | open at query time |

## Landlord growth widgets

The Dashboard growth block (rendered when
`InsightDashboardService::landlordSummary()` returns non-null)
shows three cards:

| Card | Component | Data |
| --- | --- | --- |
| Engagement score | `EngagementScoreCard.vue` | latest `landlord_engagement_scores.score`; tier-coloured (green > 70, yellow 31–70, red ≤ 30); shows 7d delta. |
| Referrals (30d) | `ReferralCountCard.vue` | count of `referrals` rows attributed to landlord in last 30 days. |
| Plan usage | `UsageRatioCard.vue` | `landlord_usage_metrics` per feature, displayed as ratio against plan limit. |

`InsightDashboardService::landlordSummary()` is exception-isolated:
any rollup failure returns `null` and the growth block silently
hides so the Dashboard never fails because of insight telemetry.

## Self-serve API

All endpoints require `Authorization: Bearer <token>` minted with
`landlord:manage` ability. Responses are JSON. The summary endpoint
is `Cache::remember`-cached for 5 minutes per landlord to absorb
mobile-app polling.

```http
GET /api/v1/landlord/insights/summary
{
  "engagement": { "current_score": 78, "delta_7d": +3, ... },
  "usage": { "features": [{ "feature": "properties", "usage": 4, "limit": 5, "ratio": 0.8 }, ...] },
  "referrals": { "count_30d": 2, "code": "..." },
  "mrr_contribution": { "current_period_kes": 1500.0, "plan_slug": "starter" }
}
```

## Exports

| Filename prefix | Format | Source |
| --- | --- | --- |
| `mrr-snapshot-YYYY-MM-DD.xlsx` | xlsx | `MrrExportController` (XlsxExportService) |
| `engagement-YYYY-MM-DD.csv` | csv | `EngagementController::export` |
| `product-events-YYYY-MM-DD.xlsx` | xlsx | `ProductEventExportController::export` (capped 10k rows) |

## Cron budget — `WorkflowLogger::measure`

`WorkflowLogger::measure(workflowName, action, Closure, ?landlordId,
?metadata)` is the timing-aware wrapper around the unchanged
`log()` method. New cron commands SHOULD adopt this signature so
the budget audit can attribute runtime:

```php
$this->workflowLogger->measure(
    workflowName: 'engagement:rollup',
    action: 'rollup',
    body: fn () => $this->rollup(),
);
```

The wrapper captures `microtime(true)` around the closure, persists
`duration_ms` + `started_at` on `workflow_runs_log`, and re-throws
on exception (with action suffixed `:error`). Legacy `log()` calls
work unchanged and write `NULL` timing — they are skipped by
`cron:budget-audit`.

## `high_cron_runtime` playbook (sev3)

1. Pull yesterday's `cron:budget-audit` output:
   `php artisan cron:budget-audit` — prints the per-command minutes
   line. The same data lives on the `cron_runtime_per_command_minutes_24h`
   gauge keyed by `command`.
2. Identify the runaway command — typically the heaviest gap from
   baseline. Common culprits: rollup jobs that grew with tenant
   count, exports stuck on a corrupt row.
3. Profile the specific command. Add an index, cap chunk size,
   shard by landlord, or split the work across additional cron
   slots.
4. Re-run `cron:budget-audit --threshold=60` to verify total
   < 60 minutes. The alert auto-resolves via `AlertFiringRecorder`
   once the total drops below threshold on the next run.

## CI gates

- `Phase36OpsDashboardTest` — operator endpoints serve JSON + Inertia.
- `Phase36LandlordGrowthTest` — Dashboard share + `/growth` subpage.
- `Phase36LandlordApiTest` — 4 sanctum endpoints + ability guard.
- `Phase36ExportsTest` — MRR xlsx + product-events xlsx.
- `Phase36CronBudgetTest` — `measure()` records timing + audit fires + resolves.
- `Phase36InsightSurfaceTest` — cron registration + alert key + lang parity.

## Deferrals

The following were considered but explicitly punted to Tier-7:

- Charting library for MRR / engagement trend lines (current CSS-table
  heatmap pattern from Phase-27 Cohort.vue is sufficient).
- WebSocket push for ops dashboard (5-minute Inertia poll is
  cheaper and sufficient).
- Email digest of weekly insight summary to landlords.
- 90-day product-event retention with cold-storage rollover.
- Cohort-based engagement decay analysis.
