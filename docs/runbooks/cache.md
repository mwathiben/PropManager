# Cache runbook

## Surface ownership

| Layer | Owner | Lineage |
|---|---|---|
| Application cache (Redis) | `Cache::tags()` / `Cache::remember()` in FinanceCacheService, BuildingCacheService, etc. | Phase 22 PERF-CACHE-1 |
| HTTP response headers (ETag + Cache-Control + Vary) | `App\Http\Middleware\SetReadCacheHeaders` | Phase 22 PERF-CACHE-2, **Phase 57 L7-CACHE** |
| Cache hit/miss observability | `cache_hit_total` / `cache_miss_total` counters via MetricsService | Phase 22 PERF-CACHE-1 |
| Shared-cache (Cloudflare / Fastly) directive | `cache.read.shared` middleware alias | **Phase 57 L7-CACHE-2** |

## Middleware aliases

### `cache.read` (Phase 22)

Emits `Cache-Control: private, must-revalidate, max-age=N` for routes listed in `config('observability.read_cache.routes')`. ETag is content MD5 — unchanged responses round-trip as 304 with body dropped.

Phase 57 additions: appends `Vary: Accept, Accept-Encoding, Cookie` so a shared cache doesn't serve one tenant's HTML to another. **Always-on** — even private responses need the Vary contribution for content-negotiation correctness.

### `cache.read.shared` (Phase 57)

Variant for truly tenant-agnostic routes (marketing landing, `/robots.txt`, public maintenance assets). Emits `Cache-Control: public, s-maxage=N, max-age=60` so a CDN at the edge can serve the cached copy to all clients for `s-maxage` seconds, while each client revalidates after 60 seconds.

**Security contract**: `cache.read.shared` MUST NOT be applied to Inertia responses or any route that includes per-tenant data. If you are unsure, use `cache.read` (private).

## Cache-key fragmentation contract

A CDN at the edge keys cache entries by URL by default. To safely cache tenant-aware responses, fragment by these cookies:

| Cookie | Source | Fragmentation role |
|---|---|---|
| `pm_session` | Laravel session | Fragments by authenticated user |
| `XSRF-TOKEN` | Laravel CSRF | Rotates frequently — Cloudflare should **strip from cache key** before key generation |
| `pm_tenant` | Phase 57+ future addition | Derived from authenticated landlord_id; fragments cache per-landlord even when session cookies rotate |

Until `pm_tenant` ships (Phase 58+ candidate), use `cache.read` (private) for all tenant-aware routes.

## Operator checks

- **Cache-Control header missing**: route not in `config('observability.read_cache.routes')` — add it if appropriate.
- **Vary header missing**: SetReadCacheHeaders is bypassed; check middleware order. Phase57L7CacheTest watchdogs the invariant.
- **Hit rate dropping**: inspect cache_hit_total / cache_miss_total counters. Common cause: a recent ETag-busting deploy (content changed → MD5 changed → 304s drop).

## Phase 57 — L7-CACHE (2026-05-18)

Adds Vary header (Accept, Accept-Encoding, Cookie) on every cache.read-tagged response + ships the `cache.read.shared` alias for truly-public routes + documents the cache-key fragmentation contract. See `phase-57-audit-prd.json` for the 18 findings.
