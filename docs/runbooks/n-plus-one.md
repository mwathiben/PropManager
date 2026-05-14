# N+1 Query Prevention (Phase-22 PERF-NPLUS1)

PropManager treats lazy-loading an Eloquent relation as a **bug**, not a
convenience. An unguarded relation access inside a loop is the classic
N+1 — invisible at 5 rows, a page-killer at 5,000.

## How it's enforced

`AppServiceProvider::boot()` calls `Model::preventLazyLoading()`. The
violation handler behaves differently per environment:

| Environment | Behaviour |
|-------------|-----------|
| `testing`   | **Throws** `LazyLoadingViolationException` — any lazy-load in a tested code path fails its test. This is the CI gate. |
| local / staging | Logs to the `security` channel (no throw — throwing on every dev page is too aggressive). |
| `production` | 1%-sampled, logs only, never throws — a missed eager-load must never take a customer page down. |

Only models retrieved as part of a **multi-row** result are guarded —
Laravel's `Builder::hydrate` stamps `preventsLazyLoading` only when the
result has more than one row, because a lone record accessed
individually is not an N+1 risk.

## The baseline allow-list

`App\Support\NPlusOneBaseline::ALLOWED` holds `Model::relation` pairs
that are *known* lazy-loads — a pair on the list is logged, not thrown,
so the gate can ship without fixing every pre-existing violation in one
commit.

**As of Phase 22 the list is empty** — the test suite surfaced zero
lazy-load violations. The `Phase22NPlusOneTest` watchdog pins the count
at 0; it may only ever stay 0 or, if a justified exception is ever
added, shrink back toward 0. A new entry means an un-fixed N+1 was
admitted and must be code-review-justified.

## When the gate fires on your change

A test now fails with `LazyLoadingViolationException: Attempted to lazy
load [relation] on model [Model]`. Fix it at the source:

1. Find the query that produced the collection.
2. Add the missing eager-load: `->with('relation')` on the query
   builder, or `->load('relation')` on an already-fetched collection.
3. Re-run the test. The PERF-CI-2 query-count budgets
   (`Phase22PerfCiTest`) confirm the fix holds — the count must stay
   constant as row count grows.

Do **not** add the pair to `NPlusOneBaseline::ALLOWED` to make the test
pass — that defeats the gate. The allow-list is for genuinely-deferred
pre-existing debt, not new violations.

## See also

- `app/Providers/AppServiceProvider.php` — the gate wiring
- `app/Support/NPlusOneBaseline.php` — the allow-list + shrink-only contract
- `tests/Feature/Performance/Phase22NPlusOneTest.php` — the watchdog
- `tests/Feature/Performance/Phase22PerfCiTest.php` — query-count budgets
