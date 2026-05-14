# Load Testing (Phase-22 PERF-LOAD)

PropManager's k6 load-test harness. The scripts live in `tests/load/`;
this runbook is how an operator runs them, reads the output, and
decides when to re-baseline.

## Prerequisites

- **k6** — install per <https://k6.io/docs/get-started/installation/>.
- **A seeded load-test landlord** — the scripts authenticate as
  `loadtest@propmanager.test`. Seed it:
  ```bash
  php artisan db:seed --class="Database\Seeders\LoadTestSeeder"
  ```
  The seeder is idempotent (no-ops if the landlord exists) and creates
  9 tenants + 27 invoices so the read paths return realistic-shaped
  result sets. The scenarios only ever **read** this landlord's data
  (or hit data-safe rejection paths), so a load run never mutates real
  tenant data.
- **A running target** — the app at `BASE_URL` (default
  `http://localhost:8000`). For a meaningful baseline, run against a
  **production-shaped** stack (php-fpm + opcache + Redis), NOT
  `php artisan serve` (single-threaded, no opcache — fine for the CI
  smoke gate, useless as a latency reference).

## The two scripts

| Script | Purpose | Who runs it |
|--------|---------|-------------|
| `smoke.js` | short, low-VU (~45s, 5 VUs). "Does the app respond and isn't catastrophically broken." | CI, every PR (`load-smoke` job) |
| `baseline.js` | staged ramp (~5 min), hot read paths + webhook ingress. Produces the reference numbers. | operator, on demand |

```bash
k6 run tests/load/smoke.js
BASE_URL=http://localhost:8000 k6 run tests/load/baseline.js
```

Every knob is env-overridable — see `tests/load/lib/config.js`:
`BASE_URL`, `LOAD_USER_EMAIL/PASSWORD`, `VUS`, `DURATION`,
`READ_VUS`, `WEBHOOK_VUS`, and the threshold overrides
(`SMOKE_P95_MS`, `SMOKE_ERROR_RATE`, `BASELINE_P95_MS`, etc.).

## Reading the output

k6 prints a summary at the end. The numbers that matter:

- **`http_req_duration` `p(95)` / `p(99)`** — the latency you ship.
  Compare against the SLO route-class budgets in
  `docs/runbooks/slo.md` (read paths < 500 ms, etc.).
- **`http_req_failed` `rate`** — fraction of 4xx/5xx. Should be ~0 for
  the read scenarios. (The `baseline.js` webhook scenario sends an
  intentionally-invalid signature; its expected 4xx is marked as
  success via a `responseCallback`, so it does NOT inflate this.)
- **`checks_succeeded`** — the `login did not 4xx/5xx` check should be
  100%. Anything less means auth is failing (throttle, CSRF, bad
  credentials).
- **`iterations` / iteration rate** — throughput.
- **THRESHOLDS block** — a `✗` means k6 exited non-zero. In CI that
  fails the `load-smoke` job.

### Login throttles + the load-test user

All VUs authenticate as the one shared `loadtest@` user. `auth.js`
logs in **once per VU** (`ensureLoggedIn`) and reuses the session — do
not change this to log in per-iteration; the 5/min `login` throttle
(keyed by `email|ip`) would 429 the run. If you genuinely need many
logins, raise `RATE_LIMIT_LOGIN` on the load-test box (the CI job does
this).

## Current baseline

> **TODO (first real baseline run):** run `baseline.js` against a
> production-shaped stack and fill this table. Until then the only
> calibrated numbers are the CI smoke gate (lenient, `artisan serve`).

| Scenario | p50 | p95 | p99 | error rate | measured on |
|----------|----:|----:|----:|-----------:|-------------|
| hot read paths | — | — | — | — | — |
| webhook ingress | — | — | — | — | — |

## When to re-baseline

Re-run `baseline.js` and update the table above after:

- a DB index change (added/dropped composite, see
  `docs/runbooks/policy-and-index.md`);
- a major framework or dependency bump;
- an N-tenant growth milestone (e.g. crossing 1,000 active leases) —
  the load profile assumptions in `phase-22-audit-prd.json` were set
  for SMB landlords and should be revisited as institutional landlords
  onboard;
- any change that `slo:report` shows pushed a route class toward its
  budget.

## CI smoke vs. a full baseline

The CI `load-smoke` job runs `smoke.js` against `php artisan serve`
with **lenient** thresholds (`SMOKE_P95_MS=3000`,
`SMOKE_ERROR_RATE=0.05`). That is deliberate — `artisan serve` is
single-threaded and not production-representative, so the CI gate only
catches "the app is broken or hung", not a real latency regression.
The real latency signal is a `baseline.js` run against a
production-shaped stack, plus the `http_request_ms` histogram +
`slo:report` from live traffic.

## See also

- `docs/runbooks/slo.md` — the route-class latency budgets the load
  results are measured against
- `docs/runbooks/autoscale-readiness.md` — load-testing the scaled
  topology
- `tests/load/README.md` — the script-level quickstart
