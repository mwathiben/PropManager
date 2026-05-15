/**
 * Phase-26 PWA-CI-1: Lighthouse CI configuration.
 *
 * Gate: the PWA category must score >= 0.9 on /dashboard (the seeded
 * load-test landlord's authenticated entry point). 1.0 is achievable
 * for our single-page Inertia app post-Phase-26, but we set the gate
 * at 0.9 to leave room for category penalties we don't fully control
 * (HTTP/2 push, response-time blips on the CI's php artisan serve,
 * etc.).
 *
 * Other categories are reported but NOT gated — this is the PWA
 * audit cycle, not the perf/a11y/seo cycle. Performance has its own
 * gates (Phase-22 PERF-CI), a11y has axe-core (Phase-23 A11Y-CI-2).
 *
 * Auth: the CI workflow ships a cookie-jar from a logged-in puppeteer
 * launch so Lighthouse hits the authenticated dashboard. See the
 * lighthouse-pwa job in .github/workflows/ci.yml.
 */

const BASE_URL = process.env.LHCI_BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = process.env.LHCI_USER_EMAIL || 'loadtest@propmanager.test';
const PASSWORD = process.env.LHCI_USER_PASSWORD || 'password';

module.exports = {
    ci: {
        collect: {
            url: [`${BASE_URL}/dashboard`],
            numberOfRuns: 1,
            puppeteerScript: './scripts/lhci-puppeteer-login.cjs',
            puppeteerLaunchOptions: {
                args: ['--no-sandbox', '--disable-dev-shm-usage'],
            },
            settings: {
                preset: 'desktop',
                // PWA audits depend on a real registered SW + manifest;
                // we boot via php artisan serve which is single-threaded
                // and slow — give the audit longer wall-clock to avoid
                // false-failing waitForLoadState in the SW handshake.
                maxWaitForLoad: 45_000,
                emulatedFormFactor: 'mobile',
                // Phase-23 a11y job exercises a11y comprehensively; here
                // we only need pwa + best-practices on the authenticated
                // dashboard.
                onlyCategories: ['pwa', 'best-practices'],
            },
        },
        assert: {
            assertions: {
                'categories:pwa': ['error', { minScore: 0.9 }],
                // best-practices is reported (non-gating) so any easy
                // wins (HTTPS, image aspect ratio, no console errors)
                // surface in the LHCI report artifact.
                'categories:best-practices': 'off',
            },
        },
        upload: {
            target: 'temporary-public-storage',
        },
    },
    // Pass user/password through to the puppeteer login script.
    env: {
        LHCI_USER_EMAIL: EMAIL,
        LHCI_USER_PASSWORD: PASSWORD,
    },
};
