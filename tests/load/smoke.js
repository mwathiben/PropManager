// Phase-22 PERF-LOAD-1: smoke load test. Short, low-VU — runs in CI on
// every PR (see .github/workflows/ci.yml load-smoke job). The
// thresholds in SMOKE_THRESHOLDS are the gate: a p95/error-rate breach
// makes k6 exit non-zero and fails the job.
//
// Data safety: hits the unauthenticated health endpoint + the hot
// authenticated READ paths for the seeded load-test landlord only.
// No write paths — see baseline.js for the (still data-safe) webhook
// ingress measurement.
import http from 'k6/http';
import { group, sleep } from 'k6';
import { BASE_URL, LOAD_USER, SMOKE_THRESHOLDS } from './lib/config.js';
import { ensureLoggedIn } from './lib/auth.js';

export const options = {
    vus: Number(__ENV.VUS || 5),
    duration: __ENV.DURATION || '1m',
    thresholds: SMOKE_THRESHOLDS,
};

export function setup() {
    // Readiness probe — fail fast + loud if the target is unreachable,
    // rather than reporting a 100% error rate as if it were a latency
    // result. NOTE: /api/health returns 503 when a dependency is
    // degraded (e.g. no Redis in the CI load-smoke job) — that is
    // still "reachable", so any non-zero status counts as up. The
    // iteration body deliberately does NOT hit /api/health: it is a
    // dependency aggregate, not a hot user path, and its 503-on-missing
    // -Redis would be counted as request failures.
    const res = http.get(`${BASE_URL}/api/health`);
    if (res.status === 0) {
        throw new Error(`target ${BASE_URL} is unreachable — is the app running?`);
    }
}

export default function () {
    // Log in once per VU, then reuse the session — re-logging every
    // iteration would hammer the login throttle and measure auth, not
    // the read paths.
    ensureLoggedIn(BASE_URL, LOAD_USER.email, LOAD_USER.password);

    group('hot read paths', () => {
        http.get(`${BASE_URL}/dashboard`);
        http.get(`${BASE_URL}/invoices`);
        http.get(`${BASE_URL}/tenants`);
    });

    sleep(1);
}
