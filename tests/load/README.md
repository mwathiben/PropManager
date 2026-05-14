# Load tests (k6)

Phase-22 PERF-LOAD-1. k6 load-test harness for PropManager's hot paths.

- `smoke.js` — short, low-VU. Runs in CI on every PR; thresholds gate.
- `baseline.js` — longer staged ramp. Operator-run, produces the
  reference numbers.
- `lib/config.js` — env-overridable knobs (BASE_URL, VUs, thresholds).
- `lib/auth.js` — Laravel session-cookie + CSRF login helper.

Quickstart:

```sh
# install k6: https://k6.io/docs/get-started/installation/
k6 run tests/load/smoke.js
BASE_URL=http://localhost:8000 k6 run tests/load/baseline.js
```

**Full instructions, the seeded load-test landlord, interpreting the
output, and re-baseline triggers live in
[`docs/runbooks/load-testing.md`](../../docs/runbooks/load-testing.md).**
