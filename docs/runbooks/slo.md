# Service Level Objectives

Phase-14 OBSERV-5: quantitative targets for `healthy`. The `/up` health probe (Phase-5 OBS-2) returns `degraded` when any of its 5 checks fails — but the on-call rotation needs to know *which* degradations page someone vs. *which* wait for the next business day. This document is that filter.

## Service tiers

### Tier 1 — Tenant-facing web (Inertia + API v1)

- **Uptime target**: 99.5% monthly = 3.6 hours of allowed downtime per month
- **p95 latency**: < 500ms for the dashboard endpoint (`/dashboard`)
- **Signals**: `/api/health` returning 200; Sentry error volume; Phase-14 OBSERV-1 `/metrics` request_duration histogram (when wired)
- **Paging rule**: 5+ consecutive `/health` failures over 5 minutes → page on-call

### Tier 2 — Payment webhook handlers (`/api/v1/payments/*`, `/api/v1/webhooks/*`)

- **Uptime target**: 99.9% monthly = 43 minutes of allowed downtime
- **p95 latency**: < 2s end-to-end (webhook receipt → reconciliation row written)
- **Signals**: Phase-12 webhook_dead_letters count; Phase-14 OBSERV-3 external-API check; MetricsService `webhook_received` + `webhook_processed` counters
- **Paging rule**: any HIGH-severity payment dead-letter, or > 10 unresolved DLQ rows for > 30 min → page on-call IMMEDIATELY (financial impact)

### Tier 3 — Background jobs (notifications, exports, schedule)

- **Uptime target**: best-effort, no SLO commitment
- **Latency**: scheduled tasks must complete within their interval (5-min tasks under 5 min, etc.)
- **Signals**: `failed_jobs` table count; queue depth via `/health` `queue` block
- **Paging rule**: failed_jobs > 25 in last 24h (Phase-5 OBS-13) → email; queue depth > 1000 → page on-call

### Tier 4 — Compliance scheduled tasks

- **Uptime target**: same-day completion required (legal obligation)
- **Tasks**: `breach:escalate-overdue` (hourly), `gdpr:process-deletions` (daily), `logs:prune` (daily), `backup:run` (daily)
- **Signals**: schedule channel log lines
- **Paging rule**: missing run for 24h → page on-call

## Error budget tracking

Each tier consumes its monthly error budget from real incidents. The Phase-13 BREACH-6 drill template (`docs/runbooks/breach-drill.md`) includes an error-budget question — drills test detection without consuming budget; real incidents consume.

## When to escalate

| Signal | First-line owner | Escalation |
|--------|------------------|------------|
| `/health` degraded for DB | DBA on-call | CTO + DBA-1 within 15m |
| `/health` degraded for Redis | Platform on-call | Platform-1 + check cache fallback |
| `/health` degraded for queue | Platform on-call | Worker scaling + investigate failed_jobs |
| `/health` degraded for webhook_dlq | Payment on-call | Page Payments lead within 30m |
| `/health` degraded for external_apis | Payment on-call | Gateway-vendor support ticket; status page update |
| Sentry release error spike | Eng on-call | Rollback eval within 30m; Phase-14 OBSERV-2 release tag identifies the bad commit |

## Targets review cadence

Quarterly. Review against the previous quarter's actuals + adjust. Targets that consistently miss by >5x are aspirational and should be lowered to something achievable + tracked toward the aspiration; targets consistently hit by >2x can be tightened.
