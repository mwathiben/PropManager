# Performance runbook (umbrella)

Performance work in PropManager lives across multiple focused runbooks. This is the entry point — start here, then drill into the specific surface.

## Related runbooks

| Runbook | Owner | Covers |
|---|---|---|
| [slo.md](slo.md) | Phase 22 | SLO budgets per route class + http_request_ms histogram |
| [load-testing.md](load-testing.md) | Phase 22 | k6 baseline + how to run + read output |
| [autoscale-readiness.md](autoscale-readiness.md) | Phase 22 | Statelessness checklist + horizontal-scale readiness |
| [n-plus-one.md](n-plus-one.md) | Phase 22 | NPlusOneBaseline contract + lazy-load detection |
| [perf-5-non-adoption.md](perf-5-non-adoption.md) | Phase 21 | SoftDeletes index reconsideration rationale |
| [cache.md](cache.md) | Phase 22 + 57 | Application + HTTP cache layers, Vary header contract |

## Phase 57 — PERF-DEEP (2026-05-18)

Layers operator-grade enforcement + ops dashboard surfaces on top of Phase 22's foundational observability. Five surfaces:

### P95-BUDGETS

`App\Console\Commands\SloEnforceBudgets` runs daily 05:00 Africa/Nairobi. Reads the `http_request_ms` histogram via `MetricsService::snapshot`, computes per-route-class p95 via linear interpolation, compares to `config('observability.slo.latency_budgets_ms')`, emits `route_p95_violation{route_class}` gauge (1=violating, 0=compliant).

`App\Services\Sre\BudgetEnforcementService::evaluate` is the pure function — takes a histogram observation + budget config map, returns per-route-class verdicts. Testable without Redis.

**Operator workflow on violation**:
1. Inspect `slo:report` output for the violating route class.
2. Inspect `db_query_ms` histogram for the same time window.
3. Inspect the slow-query weekly rollup (see SLOW-QUERY below) for related shapes.
4. If structurally unfixable, raise the budget in `config/observability.php` — but document the rationale.

### L7-CACHE

`SetReadCacheHeaders` now emits `Vary: Accept, Accept-Encoding, Cookie` on every cache.read-tagged response. Cookie covers per-tenant fragmentation; without it, a shared cache could serve one tenant's HTML to another.

New `cache.read.shared` middleware alias uses `Cache-Control: public, s-maxage=N, max-age=60` for truly tenant-agnostic routes (marketing landing, `/robots.txt`). **Never apply to Inertia responses.**

See [cache.md](cache.md) for the full cache-key fragmentation contract.

### READ-REPLICAS

`->readOnly()` macro on `Illuminate\Database\Eloquent\Builder` marks a query for read-pool routing. Today it's a no-op marker (Laravel has no per-query sticky override); when a custom resolver ships in a future phase, every marked call site is already opted in.

`App\Services\Sre\ConnectionRouter::ensureFreshRead` retries inside a `DB::transaction` (which routes to primary) when the first read returned empty AND the connection's `recordsModified` flag indicates an earlier write — handles the narrow class of write-then-immediate-read flows.

Phase 56 ChurnService::cohortsBySource + FunnelRollupService::computeSankeyPayload both use `->readOnly()` — heavy aggregates, eventual consistency OK.

### SLOW-QUERY

Phase 21 SlowQueryReport scans the storage/logs/slow-query-*.log file. Phase 57 adds a parallel SQL-table sink.

`slow_query_log_entries` captures the raw stream (landlord_id + sql_shape + duration_ms + executed_at). Opt-in via `SLOW_QUERY_PERSIST_TO_TABLE` env. `SqlShapeNormaliser::normalise` collapses queries to shape (strips literals, collapses IN-lists, truncates to 500 chars).

`slow-query:rollup` runs weekly Monday 06:30 Africa/Nairobi:
- aggregates last 7d by (sql_shape, landlord_id) into `slow_query_log_weekly_rollups` via `updateOrCreate` (idempotent re-runs);
- emits `slow_query_top_shape_count` gauge for the top 10 shapes by occurrence;
- prunes raw entries older than 30 days.

**Operator workflow**:
1. Open the rollup table for the current week, sort by occurrence_count desc.
2. Top 5 shapes are usually high-noise high-frequency queries (login, dashboard).
3. The 6th–20th positions are where actionable performance work hides — those are queries that aren't pervasive but are slow.

### INDEX-AUDIT

`index-audit:scan` runs daily 04:30 Africa/Nairobi. Iterates `IndexAuditCatalog::queries()` (8 hot-path Builder factories), runs raw EXPLAIN against each, detects:
- `type='ALL'` (full tablescan)
- `rows > 5000` (large row estimate)

Emits `db_missing_index_hint{query_label}` gauge per query (1=hint, 0=clean).

**Operator workflow on hint**:
1. Read the EXPLAIN output for the labelled query (run `index-audit:scan -v` locally to see it).
2. Decide if a new index is justified (weigh write cost vs read benefit).
3. If yes, ship the migration + drop the catalog entry (or keep it as a watchdog).
4. If no, document the rationale (Phase 21 perf-5-non-adoption.md is the template).

## Cross-references

Phase 15 PERF-5 (SoftDeletes index lineage) → Phase 21 SlowQueryReport → Phase 22 PERF-SLO/CACHE/NPLUS1 foundations → **Phase 57 PERF-DEEP** (operator-grade enforcement + ops dashboards).
