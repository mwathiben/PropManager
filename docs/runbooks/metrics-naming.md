# Metric (gauge/counter) naming convention

Phase-69 GAUGE-NAMING codified this after Phase-65 review flagged the
legal-hold gauges as inconsistent. `Phase69GaugeNamingTest` enforces the
format rules over every `config/alerts.php` gauge.

## Rules

1. **snake_case, lowercase**: `^[a-z][a-z0-9_]*$`. No camelCase, spaces,
   or leading digit.
2. **Domain-prefixed**: start with the owning domain — `inbox_`, `legal_hold_`,
   `subscription_`, `webhook_`, `queue_`, `cache_`, `backup_`, `nps_`,
   `onboarding_`, `parts_`, `landlord_`, etc.
3. **Carry a unit / aggregate token** so the name says what it measures.
   Recognized tokens:
   `count`, `total`, `rate`, `ratio`, `score`, `depth`, `bytes`, `hours`,
   `minutes`, `seconds`, `ms`, `usd`, `days`, `age`, `drift`, `burn`, `up`,
   `percent`, `p50`/`p90`/`p95`/`p99`.
4. **Window as a trailing qualifier** when the value is over a window:
   `_24h`, `_1h`, `_7d`, `_30d`, `_90d` (e.g. `failed_jobs_count_24h`).
5. **Dimensions are labels, not name explosion**: per-landlord / per-subject
   variance goes in labels (`landlord_id`, `subject_type`), never a new
   gauge per value.

## The legal-hold gauges are intentionally separate (not duplicates)

Phase-65 review asked whether `messages_legal_hold_count`,
`files_retention_held_count`, and `retention_legal_hold_exclusions_count`
should be unified. They must NOT be — they measure **distinct quantities**:

| Gauge | Measures |
|---|---|
| `messages_legal_hold_count` | held MessageThreads excluded by the message retention cron |
| `files_retention_held_count{subject}` | held Documents excluded by the file retention cron, per subject |
| `retention_legal_hold_exclusions_count{subject_type}` | the cross-type aggregate (`legal-hold:audit-exclusions`) |

Each is emitted by a different cron at a different layer; collapsing them
would lose the per-layer signal. They are consistent with the convention
(domain-prefixed + `count`).

## Renaming live gauges

Renaming a gauge breaks existing dashboards/alerts. Treat the names as a
stable contract — prefer adding a new correctly-named gauge and deprecating
the old one over an in-place rename. The existing 23 alert gauges all
**satisfy** the guard (the `GRANDFATHERED` allowlist is intentionally empty);
new ones must satisfy it too.

Note: `age` and `score` are valid measurement tokens but generic — always
pair them with a clear domain prefix (`backup_age_hours`,
`landlord_engagement_score`) so the gauge reads unambiguously. The boolean
`_up` liveness idiom (`dependency_up`) is allowlisted exactly rather than via
a generic token, so names like `gateway_warm_up` are correctly rejected.

## Cross-references

- `config/alerts.php` — the alert registry (each entry's `gauge`)
- `docs/runbooks/alert-thresholds.md` — the threshold catalog
- `docs/runbooks/testing.md` — test hygiene gates
