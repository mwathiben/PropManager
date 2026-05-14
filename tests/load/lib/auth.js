// Phase-22 PERF-LOAD-1: shared login helper. PropManager uses Laravel
// session-cookie auth with CSRF protection. Laravel's VerifyCsrfToken
// accepts the X-XSRF-TOKEN header (the URL-decoded value of the
// XSRF-TOKEN cookie), so the flow is:
//   1. GET /login  -> server sets the XSRF-TOKEN + session cookies
//      (k6 keeps them in the per-VU cookie jar automatically)
//   2. POST /login with the decoded token in the X-XSRF-TOKEN header
// After this the VU's cookie jar carries an authenticated session for
// every subsequent request.
//
// IMPORTANT: call ensureLoggedIn() — not login() directly — from the VU
// function. It logs in once per VU and reuses the session. Logging in
// every iteration would (a) hammer the login throttle and (b) measure
// auth latency instead of the read paths under test.
import http from 'k6/http';
import { check } from 'k6';

// Module scope is per-VU in k6 (each VU is its own isolate), so this
// flag tracks "has THIS VU logged in yet".
let loggedIn = false;

export function login(baseUrl, email, password) {
    http.get(`${baseUrl}/login`);

    const jar = http.cookieJar();
    const cookies = jar.cookiesForURL(`${baseUrl}/login`);
    const token =
        cookies['XSRF-TOKEN'] && cookies['XSRF-TOKEN'].length > 0
            ? decodeURIComponent(cookies['XSRF-TOKEN'][0])
            : '';

    const res = http.post(
        `${baseUrl}/login`,
        { email: email, password: password },
        { headers: { 'X-XSRF-TOKEN': token }, redirects: 1 },
    );

    check(res, {
        'login did not 4xx/5xx': (r) => r.status < 400,
    });

    return res;
}

export function ensureLoggedIn(baseUrl, email, password) {
    if (loggedIn) {
        return;
    }
    login(baseUrl, email, password);
    loggedIn = true;
}
