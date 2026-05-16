# Cost runbook — Phase 33

## Overview

Phase 33 turns the platform from **observable** (Phases 22 + 32) into
**attributable**. Every line item operators see in the AWS / SMS / log-
ingest bill can now be traced back to a landlord and a workload class.

Surface map (all scheduled to `Africa/Nairobi`, `onOneServer`):

| Cron                       | Cadence            | Writes / emits                           |
|----------------------------|--------------------|------------------------------------------|
| `cost:attribute`           | daily 03:30        | `landlord_estimated_cost_kes`            |
| `query:cost-audit`         | daily 03:45        | `query_scan_to_return_ratio_p90`         |
| `cache:hit-rate-audit`     | daily 03:50        | `cache_hit_rate_ratio`                   |
| `log:volume-audit`         | daily 03:55        | `landlord_log_bytes_24h` + median + p95  |
| `storage:tier-policy`      | weekly Sun 04:30   | `storage_bytes_by_tier_total`            |
| `storage:cost-audit`       | weekly Sun 05:00   | `storage_estimated_monthly_kes`          |

Three alert keys fire from this surface: `high_query_scan_ratio`,
`low_cache_hit_rate`, `high_landlord_log_volume`.

## Per-landlord cost attribution

`landlord_usage_metrics` rolls up daily counters per (landlord, metric).
`LandlordUsageMetricRecorder::add` is the only writer — it uses atomic
`INSERT ... ON DUPLICATE KEY UPDATE` so concurrent requests on the same
day merge instead of clobbering each other.

`cost:attribute` reads the last 30 days, applies the per-unit prices from
`config/cost.php`, and emits `landlord_estimated_cost_kes{landlord_id}`
for the top 50.

### Recalibration cadence

Per-unit prices live in `config/cost.php` under `rates`. Recalibrate
**quarterly** against the actual AWS + Africa's Talking + cron-server
invoices. Bump `calibrated_at` and `usd_to_kes_rate` in the same commit
so the audit trail explains the deltas.

## Query cost — `query:cost-audit`

`TrackQueryCost` middleware samples requests with more than 10 queries
and writes a row to `query_cost_logs` recording `query_count`,
`rows_scanned` (heuristic 100 per SELECT), `rows_returned`. The audit
computes a P50/P90 `scan_to_return_ratio` per route class.

| Pattern                                | Likely cause                          |
|----------------------------------------|---------------------------------------|
| High ratio, low query count            | Missing index (one query scans a lot) |
| High ratio, high query count           | N+1 (many tiny queries each scanning) |
| Low ratio, high query count            | Chatty controller — collapse with eager loading |

Alert fires when P90 ratio exceeds the configured threshold (default
1000). Severity sev3, email-only — investigate within 24h.

## Cache hit rate — `cache:hit-rate-audit`

Reads `cache_hit_total` and `cache_miss_total` counters (Phase-22
`CacheMetrics::record`) keyed by `{cache=X,type=Y}` label, computes the
per-bucket ratio, and fires `low_cache_hit_rate` (sev3) when any bucket
drops below 0.5.

### Remediation order

1. **Confirm hit rate fell, not denominator** — a cache with 10 hits and
   0 misses has 100% hit rate but is barely used.
2. **Check TTL** — a recent TTL change can crater hit rate.
3. **Check eviction** — Redis `maxmemory-policy` may be ejecting hot
   keys; bump RAM or change policy.
4. **Check key cardinality** — a key like `report:{user}:{date}` with
   high cardinality is effectively no cache.

See [policy-and-index runbook](policy-and-index.md) for cache key
design rules.

## Storage tier policy — `storage:tier-policy` + `storage:cost-audit`

`storage_tier_policies` holds the lifecycle rules. The tier-policy cron
walks each active policy and buckets every file under the prefix into
`current` (younger than `max_age_days`) or `target` (older).
`storage:cost-audit` multiplies the bucket gauges by the per-tier rate.

### Decision tree

```
                    +--------------------+
                    | data age           |
                    +--------------------+
                              |
                  +-----------+-----------+
                  |                       |
            < 90 days                 >= 90 days
                  |                       |
            STANDARD                +-----+-----+
                                    |           |
                              < 365 days   >= 365 days
                                    |           |
                                STANDARD_IA   GLACIER
                                              (or DEEP_ARCHIVE if
                                               legal-only retention)
```

### KES math

At 2026-04-01 calibration with USD/KES = 145:

| Tier               | $/GB/mo  | KES/GB/mo |
|--------------------|----------|-----------|
| STANDARD           | 0.023    | 3.34      |
| STANDARD_IA        | 0.0125   | 1.81      |
| GLACIER            | 0.004    | 0.58      |
| DEEP_ARCHIVE       | 0.00099  | 0.14      |

For 1 TB sitting in STANDARD that could be GLACIER → 1024 × (3.34 − 0.58)
≈ KES 2,826/month saved.

### Applying a policy

We do **not** auto-move objects from a cron. After the audit shows
target-bucket bytes worth moving, an operator applies a real S3
`LIFECYCLE` rule via Terraform — AWS executes atomically and emits its
own move-completion metric.

## Log volume — `log:volume-audit`

Catches the noisy-minority problem: typically 5% of landlords drive 80%
of log writes (almost always a misconfigured webhook that retries on
500). `log_volume_daily` rolls up per landlord per day; the cron emits
top-20 gauges plus median + P95, and fires `high_landlord_log_volume`
(sev4) when any single landlord exceeds 5× the median.

### Investigation playbook

1. From the alert metadata, grab the offender's `landlord_id`.
2. Pull the last 24h of logs filtered by that landlord_id.
3. Group by log message — look for retry loops, validation failures,
   stack traces.
4. Common fixes: disable a runaway webhook subscription, add a
   circuit breaker around an upstream call, fix the underlying error.

## CI gates

- `Phase33CostSurfaceTest` asserts every cron in the table above is
  registered with its expected cadence + `Africa/Nairobi`, the three
  alert keys are present, and `lang/{en,sw}/cost.php` parity holds.
- `Phase24CiTest` (existing) re-verifies the lang/{en,sw}/cost.php key
  order matches across locales.

## Deferrals (out of scope for Phase 33)

- Actual S3 LIFECYCLE move automation — too risky from a cron.
- Sentry dynamic sample rate adjustment.
- Per-tenant log-rate ceilings (would block writes, not just observe).
