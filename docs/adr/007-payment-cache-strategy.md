# ADR-007: Finance Cache Strategy

## Status

Accepted (PAY-V2.1-012)

## Context

PropManager caches finance statistics and reports via `FinanceCacheService` to reduce database load on frequently accessed dashboard and finance pages. Three issues existed:

1. **Silent report invalidation failure**: `invalidateReports()` used Redis `KEYS` pattern matching, but the project runs on the `database` cache driver. On non-Redis drivers, the method returned early, leaving stale report data for up to 10 minutes (the `REPORTS_TTL`).

2. **No post-mutation cache warming**: After invalidation, the first page load hit cold cache and executed expensive aggregate queries. With 7 stat types per landlord, this caused noticeable latency spikes.

3. **No cache observability**: No logging for hit/miss rates or invalidation events, making it impossible to diagnose cache-related performance issues.

## Decision

### Report Key Registry (replaces Redis KEYS pattern)

When `rememberReport()` caches a report, it appends the cache key to a per-landlord registry array stored at `finance:report_keys:{landlordId}`. On invalidation, `invalidateReports()` reads the registry, forgets each key, then clears the registry itself.

This works on ALL cache drivers (database, file, redis, memcached).

### Post-Mutation Cache Warming via Queued Job

`WarmFinanceCacheJob` implements `ShouldBeUnique` with a 10-second uniqueness window. High-frequency observers (Payment, Invoice, Expense, Refund) dispatch this job on `created` events with a 2-second delay after invalidation. The `updated` and `deleted` events on these same observers use invalidation-only (no warming), since update/delete events are less frequent and warming on every attribute change would flood the queue.

Low-frequency observers (LateFee, LateFeePolicy, Lease, Building) use invalidation-only for all events.

The warming job calls 5 top-level stat methods. `getHubStats` transitively caches `overview` and `arrears` stats via internal calls to `getOverviewStats` and `getArrearsStats`, so all 7 invalidated key types are re-warmed.

### Cache Log Channel

A dedicated `cache` daily log channel at `storage/logs/cache.log` (7-day retention) records hit/miss events and invalidation actions. Hit/miss detection uses zero-overhead callback wrapping rather than an extra `Cache::has()` read.

### Cache Key Inventory

| Key Pattern | TTL | Setter | Invalidator |
|-------------|-----|--------|-------------|
| `finance:{type}:{landlordId}` | 300s | `rememberStats()` | `invalidateStats()` |
| `finance:{type}:{landlordId}:{suffix}` | 300s | `rememberStats()` | `invalidateStats()` |
| `finance:report:{type}:{landlordId}:{md5}` | 600s | `rememberReport()` | `invalidateReports()` |
| `finance:report_keys:{landlordId}` | 660s | `registerReportKey()` | `invalidateReports()` |
| `finance:superadmin:{type}` | 300s | `rememberSuperAdminStats()` | `invalidateSuperAdminStats()` |

### Invalidation Flow

```
Model Event (created/updated/deleted)
  → Observer
    → FinanceCacheService::invalidateAndWarm() [high-freq]
      or FinanceCacheService::invalidateForLandlord() [low-freq]
      → invalidateStats(): Cache::forget() × 7 stat keys
      → invalidateReports(): read registry → Cache::forget() each → forget registry
      → Log::channel('cache')->info('Cache invalidated')
      → WarmFinanceCacheJob::dispatch()->delay(2) [high-freq only]
        → FinanceStatsService: getHubStats, getDepositStats, getLateFeeStats, getExpenseStats, getMonthlyTrend
```

## Consequences

### Positive

- Report invalidation works correctly on the database cache driver (bug fixed)
- Post-payment page loads serve warm cache instead of cold queries
- `ShouldBeUnique` with 10s window prevents warming floods during bulk imports
- Cache log channel enables production monitoring of hit rates
- Zero-overhead hit/miss detection (no extra cache reads)

### Negative

- Report key registry adds a small write on each `rememberReport()` call
- Registry array grows with distinct filter combinations per landlord (bounded by TTL)
- Warming job adds queue pressure (mitigated by uniqueness constraint)

### Neutral

- Existing `finance:warm-cache` artisan command remains for manual/scheduled warming
- `WithETag` HTTP-level caching on FinancesController is unaffected
- Super admin cache keeps 5-minute TTL without explicit invalidation (acceptable for admin dashboard)

## References

- PAY-V2.1-012: Review and Enhance Payment Cache Strategy
- [Laravel Cache Documentation](https://laravel.com/docs/12.x/cache)
- [Best Practices for Cache Invalidation in Laravel](https://inspector.dev/best-practices-for-cache-invalidation-in-laravel/)
- ADR-006: Payment Idempotency Pattern (prior ADR format)
