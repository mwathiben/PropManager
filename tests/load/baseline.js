// Phase-22 PERF-LOAD-1: baseline load test. Longer staged ramp, run by
// an operator (not CI) to produce the repeatable reference numbers in
// docs/runbooks/load-testing.md. Re-run after an index change, a major
// dependency bump, or an N-tenant growth milestone.
//
// Scenarios cover the hot read paths AND the hot write path — the
// payment-webhook ingress. The webhook POST deliberately carries an
// INVALID signature: it exercises the signature-validation middleware
// (a real latency surface, bursty at month-start rent cycles) and is
// 100% data-safe because the request is rejected before any handler
// runs. The rejection is the EXPECTED outcome, so those requests use a
// responseCallback that treats the 4xx as success — otherwise the
// intentional rejections would inflate http_req_failed.
import http from 'k6/http';
import { group, sleep } from 'k6';
import { BASE_URL, LOAD_USER, BASELINE_THRESHOLDS } from './lib/config.js';
import { ensureLoggedIn } from './lib/auth.js';

// The webhook ingress is supposed to reject our invalid signature —
// 401/403/422 are the SUCCESS case for that scenario.
const webhookExpected = http.expectedStatuses(401, 403, 419, 422);

export const options = {
    thresholds: BASELINE_THRESHOLDS,
    scenarios: {
        hot_read_paths: {
            executor: 'ramping-vus',
            exec: 'readPaths',
            startVUs: 0,
            stages: [
                { duration: '1m', target: Number(__ENV.READ_VUS || 20) },
                { duration: '3m', target: Number(__ENV.READ_VUS || 20) },
                { duration: '1m', target: 0 },
            ],
        },
        webhook_ingress: {
            executor: 'constant-vus',
            exec: 'webhookIngress',
            vus: Number(__ENV.WEBHOOK_VUS || 5),
            duration: '5m',
        },
    },
};

export function setup() {
    const res = http.get(`${BASE_URL}/api/health`);
    if (res.status === 0) {
        throw new Error(`target ${BASE_URL} is unreachable — is the app running?`);
    }
}

export function readPaths() {
    ensureLoggedIn(BASE_URL, LOAD_USER.email, LOAD_USER.password);

    group('hot read paths', () => {
        http.get(`${BASE_URL}/dashboard`);
        http.get(`${BASE_URL}/invoices`);
        http.get(`${BASE_URL}/tenants`);
        http.get(`${BASE_URL}/tenants-hub`);
        http.get(`${BASE_URL}/payments-hub/collection`);
    });

    sleep(1);
}

export function webhookIngress() {
    // Invalid signature on purpose — measures the validation-middleware
    // ingress cost without ever reaching a handler or mutating data.
    // responseCallback marks the 4xx rejection as the expected success.
    group('webhook ingress (rejected)', () => {
        http.post(
            `${BASE_URL}/webhooks/paystack`,
            JSON.stringify({ event: 'charge.success', data: {} }),
            {
                headers: {
                    'Content-Type': 'application/json',
                    'x-paystack-signature': 'load-test-invalid-signature',
                },
                responseCallback: webhookExpected,
            },
        );
    });

    sleep(0.5);
}
