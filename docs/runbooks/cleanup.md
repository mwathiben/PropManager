# Cleanup runbook — Phase 38 [DEFER-CLEANUP-2]

## Overview

Defensive-debt sweep cycles run roughly every 10 audit cycles (Phase 21
was the first, Phase 38 the second, Phase ~54 will be the third).
Their job is to catch the latent bugs that feature-focused audits
miss — Laravel-version migration leftovers, build-time fragility,
observability noise, and accumulated test-health drift.

Phase 38 was triggered by two visible production-class incidents on
2026-05-16:
1. Phase-30 layout typo silently froze the Vite bundle for 36h.
2. AdminController.php:36 used the Laravel-9 `$this->middleware()`
   pattern that L11 removed, 500ing every `/admin/*` request.

Plus scout discoveries: duplicate `help.search` route name breaking
`route:cache`, MetricsService spamming "Class Redis not found" on
every request, useHelpDrawer import case-mismatch that would break
Linux deploy.

## Surface map

| Surface | What it catches | Where |
| --- | --- | --- |
| Route-name dedup | `route:cache` LogicException on duplicate names | `Phase38CleanupSurfaceTest::test_route_cache_compiles_without_collision` |
| MetricsService fallback | "Class Redis not found" log spam | `MetricsService::redisAvailable()` static gate |
| Import-case scanner | `@/CapitalCase` imports that break on Linux | `scripts/check-import-case.mjs` + `npm run build` prefix |
| Test-health ratchet | New test failures sliding in | `Phase38CleanupSurfaceTest::test_suite_error_count_at_or_below_baseline` |
| Bundle freshness audit | Stale `public/build/` vs newer FE commits | `bundle:freshness-audit` cron + `stale_bundle_warning` sev4 |
| Lefthook pre-commit | Wrong-case imports + broken Vite build | `lefthook.yml` |

## Route conflict detection

`php artisan route:cache` walks every route and serializes by name.
Two routes with the same `->name(...)` throw `LogicException: Unable
to prepare route [X] for serialization. Another route has already
been assigned name [X]`. Production lose route caching entirely
(10-50ms per request regression).

Phase 38 dedup: Phase-31 `/api/help/{contextual,search}` were
renamed to `help.api.{contextual,search}` to free the name for the
pre-Phase-31 `/help/search → help.search` legacy public-help-portal
endpoint that `Pages/Help/Index.vue` consumes via `route()`.

The CleanupSurfaceTest watchdog walks the route collection
deduplicating by name — any future duplicate fires before deploy.

## Metrics-driver fallback playbook

`MetricsService` reads the cache driver and calls Redis. Before
Phase 38 it called Redis::connection unconditionally and threw on
every request when phpredis wasn't installed. Now:

1. `MetricsService::redisAvailable()` checks once per process via
   `extension_loaded('redis')` OR `class_exists(\Predis\Client::class)`.
   Cached statically.
2. All five public methods (`increment`, `observe`, `gauge`,
   `snapshot`, `gaugeSnapshot`) noop when unavailable.
3. ONE notice per day via `Cache::add('metrics:driver-unavailable-
   notice', ttl=86400)` — operators see one entry, not thousands.

To enable metrics in dev/test, install predis (Phase 38 added it as
a dev dependency):

```bash
composer require predis/predis --dev
```

To enable in prod, install phpredis (PECL extension):

```bash
pecl install redis
echo 'extension=redis' >> /etc/php/8.4/cli/conf.d/redis.ini
```

## Case-sensitivity hygiene

Windows resolves `@/Composables/useHelpDrawer` and
`@/composables/useHelpDrawer` to the same file. Linux production
fails the second one with "Module not found". This has caused 3
bugs across phases 30, 31, 38.

Run `npm run check:imports` (or `node scripts/check-import-case.mjs`)
before any FE-touching commit. The script walks every `.ts/.tsx/
.vue/.js` file under `resources/js`, regex-extracts `from '@/...'`
imports, and validates each DIRECTORY segment matches actual
filesystem case. The last segment (filename) is intentionally
skipped because bundlers accept `.ts/.vue/.d.ts` extensions
interchangeably.

`npm run build` runs the scanner first.

## Test-health ratchet

Phase-38 scout measured 90 errors + 9 failures across 2633 tests
(3.8% red rate). Phase 21's cleanup didn't ratchet, and 16 cycles of
new tests didn't catch the drift.

Phase-38 ratchet:
- CI runs `php artisan test --log-junit storage/app/junit.xml`
- `Phase38CleanupSurfaceTest::test_suite_error_count_at_or_below_baseline`
  reads the junit XML, sums `errors + failures` attributes, asserts
  `<= 99`.
- Every fix-the-test PR lowers the constant.
- Once 0 is reached, the assertion stays `=== 0` forever.

To raise the baseline (legitimate xfail only, e.g. flaky external
integration test pending vendor fix), update the constant in
`Phase38CleanupSurfaceTest::test_suite_error_count_at_or_below_baseline`
WITH an inline comment explaining why.

## Build-time freshness

The Vite bundle in `public/build/` is the source of truth for what
the browser executes. Stale bundles silently break dashboards — the
Phase-30 layout typo froze the bundle for 36h before a user clicked
a Phase-37 button.

Two layers of defence:

1. **Local pre-commit (lefthook)** — `npm run build` runs on every
   commit that touches `resources/js`, `vite.config.js`, or
   `package.json`. SKIP_VITE=1 overrides; LEFTHOOK=0 disables all
   hooks (only for hotfix backports).
2. **Daily cron (`bundle:freshness-audit`)** — at 04:55 Africa/
   Nairobi, compares `public/build/manifest.json` mtime against
   the newest git commit touching FE files. Fires
   `stale_bundle_warning` (sev4) when gap > 24h.

Install lefthook locally:
```bash
choco install lefthook       # Windows
brew install lefthook        # macOS
asdf plugin add lefthook     # Linux
lefthook install             # Once at repo root
```

## CI gates

| Gate | What | Where |
| --- | --- | --- |
| Phase38CleanupSurfaceTest | 8 invariants | `tests/Feature/Cleanup/` |
| `npm run check:imports` | Case-sensitivity | Pre-build hook |
| `lefthook pre-commit` | Build + import scanner | `lefthook.yml` |
| `bundle:freshness-audit` | Stale bundle detection | Daily cron |

## Cosmetic noise: Kaspersky / Norton / Avast AV CSP violations

If you see browser-console errors like:

```
vue-core-XXX.js:4 Executing inline script violates the following CSP
directive 'script-src 'self' 'nonce-XXX' https://gc.kis.v2.scr.kaspersky-labs.com'
```

…that's your operating system's antivirus extension injecting its
own tracking script. The AV adds its tracking-origin to `script-src`
via HTTP header rewrite BUT injects inline scripts that don't carry
the per-request nonce → browser blocks → red console error.

**Action**: identify the offending origin (`kaspersky-labs.com`,
`avast.com`, `nortonlifelock.com`), confirm it's NOT our domain,
ignore. Optionally disable the AV web-shield extension for
localhost development.

This is NOT our application's bug; the CSP nonce wiring is
correct (see `SecurityHeaders.php:85-138`).

## Phase 21 + Phase 38 retrospective

| Phase | Year | Cycles since prior | Key findings |
| --- | --- | --- | --- |
| Phase 21 | 2026-Q1 | 20 cycles | 18 carry-forward deferrals across phases 11-20 (AUTHZ-FRONT, FRONT-UX, OBSERV) |
| Phase 38 | 2026-05 | 16 cycles | Laravel-11 migration leftovers, build fragility, observability noise, 99 test errors |

Average ~16-20 cycles per cleanup. Next due Phase ~54.

## Tier-7 next-cycle [GROWTH-VENDORS-2] handoff notes

Phase 39 ships:
- AnalyticsForwarder + batched product_events replay to Amplitude/
  Mixpanel/Heap
- Stripe gateway parity for non-KE markets
- Multi-variant ANOVA significance + Bayesian sequential analysis
- iOS Safari push enablement guide
- Cold-storage rehydration read path via S3 select / Athena
- Server-side click_url propagation across remaining listeners

Phase 38 cleared the deck — Phase 39 builds on stable foundation:
- Route-cache works → vendor URLs can be cached
- MetricsService noops cleanly → vendor metrics don't compete
- Import case validated → vendor SDK imports don't drift
- Test ratchet at 99 → vendor work has a clean baseline to ratchet down
- Bundle freshness watchdog → vendor JS doesn't silently freeze

---

## Phase 53 [DEFER-CLEANUP-3] (2026-05-18)

Tier-7 cycles 4-16 (Phases 40-52) accumulated 7 "alert row + tracker
exist, gauge wiring deferred" carry-overs. Phase 53 closes the lot:

### Closures

| Carry-over | Source phase | Closeout commit |
| --- | --- | --- |
| `tenant_kyc_blocked_count` gauge emitter | Phase 48 | `feat(phase-53): GAUGE-WIRING wire 3 prom gauges` |
| `report_render_failure_count` exception counter | Phase 50 | same |
| `i18n_translation_spend_usd_24h` Cache scrape | Phase 52 | same |
| `vue_preview_poll_pause_count` POST telemetry | Phase 51 | `feat(phase-53): VUE-TELEMETRY beforeunload sendBeacon` |
| `Phase29CiTest::test_workflow_logger_writes_a_row_with_full_metadata` firstOrFail pollution | Phase 45 | `fix(phase-53): TEST-DEBT Phase29CiTest firstOrFail + baseline 99→98` |
| Phase 44 RTL Playwright suite CI wiring + runbook | Phase 44 | `ci(phase-53): RTL-BASELINES wire suite into CI + seeding runbook` |
| Phase 44 ESLint plugin warn → baseline-gated | Phase 44 | `ci(phase-53): ESLINT-RATCHET shrink-only baseline for propmanager rules` |

### Skipped-test audit (TEST-DEBT-2)

19 `markTestSkipped` sites across 9 files were reviewed. None re-enabled in
Phase 53 because all fall into one of three categories:

- **Env-dependent (legitimate skip)**: Phase22LoadTest (k6), Phase38CleanupSurfaceTest
  (junit.xml artifact-presence), Phase27GoldenQueriesTest (fixture-presence —
  auto-runs when fixtures are present, all 4 already committed),
  InteractsWithMailpit (Mailpit running), PaymentReceivedBroadcastTest
  (full dev env).
- **Pre-existing scope deeper than DEFER-CLEANUP-3**: AuthApiTest (10 skips,
  'API routes not yet implemented'), TenantApiTest (2 skips, custom
  notifications schema), PaymentControllerTest (1, schema change needed),
  TransactionRollbackTest (1, Phase-38 DEFER-TEST-HEALTH-3 followup 404).
- **Already auto-running** when their guard condition resolves: see
  category 1 — these run silently on CI when the prerequisite is met.

### Test-error ratchet ledger (TEST-DEBT-3)

`Phase38CleanupSurfaceTest::test_suite_error_count_at_or_below_baseline`
constant 99 → 98 after the Phase29CiTest firstOrFail fix nets -1
error+failure. Shrink-only.

### ESLint baseline (ESLINT-RATCHET-1)

`.eslint-baseline.json` pinned on 2026-05-18:

```
no-hardcoded-english-strings: 3751
no-ltr-class: 0  (Phase 44 codemod cleared en-masse)
```

Both rules stay at `warn` severity so `npm run lint` exits cleanly in
the dev workflow. CI gate is `npm run lint:baseline`
(`scripts/lint-baseline.mjs`) which counts violations per rule + fails
when current count exceeds baseline. Lower the baseline as commits fix
real violations; never raise.

### RTL baseline policy (RTL-BASELINES-1)

`tests/a11y/rtl/__screenshots__/.gitkeep` pins the baseline directory.
Actual 8 baseline pngs are operator-seeded on first run per
`docs/runbooks/rtl-snapshots.md` (boot dev env, run `npm run test:rtl:update`,
commit). CI's strict-compare passes harmlessly on the first run because
Playwright's `toHaveScreenshot()` writes the baseline when none exists.
