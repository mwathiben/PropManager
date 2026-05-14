// Phase-22 PERF-LOAD-1: shared k6 configuration. Every knob is
// env-overridable so CI and a laptop run the SAME scripts with
// different settings — see docs/runbooks/load-testing.md.

export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

// A dedicated load-test landlord. Scenarios must only ever read this
// account's data (or hit data-safe rejection paths) so a load run never
// mutates real tenant data. Seed it with the LoadTestSeeder first.
export const LOAD_USER = {
    email: __ENV.LOAD_USER_EMAIL || 'loadtest@propmanager.test',
    password: __ENV.LOAD_USER_PASSWORD || 'password',
};

// Smoke: short, low-VU, runs in CI on every PR. Thresholds are the
// gate — a breach makes k6 exit non-zero and fails the CI job.
//
// Defaults are tuned for a real (php-fpm + opcache) target. CI runs
// against `php artisan serve` (single-threaded, no opcache), which is
// NOT production-representative — the CI job overrides SMOKE_P95_MS /
// SMOKE_ERROR_RATE to lenient values. The smoke test's job in CI is
// "the app responds and isn't catastrophically broken/hung"; the real
// latency numbers come from an operator baseline.js run.
export const SMOKE_THRESHOLDS = {
    http_req_duration: [`p(95)<${Number(__ENV.SMOKE_P95_MS || 800)}`],
    http_req_failed: [`rate<${Number(__ENV.SMOKE_ERROR_RATE || 0.01)}`],
};

// Baseline: longer staged ramp, run by an operator. Looser ceilings —
// the point is a repeatable reference number, not a hard gate.
export const BASELINE_THRESHOLDS = {
    http_req_duration: [
        `p(95)<${Number(__ENV.BASELINE_P95_MS || 1500)}`,
        `p(99)<${Number(__ENV.BASELINE_P99_MS || 3000)}`,
    ],
    http_req_failed: [`rate<${Number(__ENV.BASELINE_ERROR_RATE || 0.02)}`],
};
