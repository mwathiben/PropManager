// Phase-22 PERF-LOAD-1: shared k6 configuration. Every knob is
// env-overridable so CI and a laptop run the SAME scripts with
// different settings — see docs/runbooks/load-testing.md.

export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

// A dedicated load-test landlord. Scenarios must only ever read this
// account's data (or hit data-safe rejection paths) so a load run never
// mutates real tenant data. Seed it before running — see the runbook.
export const LOAD_USER = {
    email: __ENV.LOAD_USER_EMAIL || 'loadtest@propmanager.test',
    password: __ENV.LOAD_USER_PASSWORD || 'password',
};

// Smoke: short, low-VU, runs in CI on every PR. Thresholds are the
// gate — a breach makes k6 exit non-zero and fails the CI job.
export const SMOKE_THRESHOLDS = {
    http_req_duration: ['p(95)<800'],
    http_req_failed: ['rate<0.01'],
};

// Baseline: longer staged ramp, run by an operator. Looser ceilings —
// the point is a repeatable reference number, not a hard gate.
export const BASELINE_THRESHOLDS = {
    http_req_duration: ['p(95)<1500', 'p(99)<3000'],
    http_req_failed: ['rate<0.02'],
};
