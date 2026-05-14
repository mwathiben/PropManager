// Phase-22 PERF-LOAD-1: shared login helper. PropManager uses Laravel
// session-cookie auth with CSRF protection. Laravel's VerifyCsrfToken
// accepts the X-XSRF-TOKEN header (the URL-decoded value of the
// XSRF-TOKEN cookie), so the flow is:
//   1. GET /login  -> server sets the XSRF-TOKEN + session cookies
//      (k6 keeps them in the per-VU cookie jar automatically)
//   2. POST /login with the decoded token in the X-XSRF-TOKEN header
// After this the VU's cookie jar carries an authenticated session for
// every subsequent request.
import http from 'k6/http';
import { check } from 'k6';

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
