/**
 * Phase-26 PWA-CI-1: Lighthouse CI configuration.
 *
 * Gate: best-practices must score >= 0.9 on /dashboard (the seeded
 * load-test landlord's authenticated entry point).
 *
 * History: this gate originally asserted categories:pwa >= 0.9, but
 * Lighthouse 12 (bundled by @lhci/cli >= 0.13) REMOVED the PWA
 * category entirely, so that assertion failed structurally with
 * "auditRan expected >= 1" — it could never pass. The PWA-specific
 * guarantees (SW registers + takes control, offline fallback,
 * manifest installability) are enforced by the pwa-smoke Playwright
 * job (tests/pwa/sw.spec.ts + tests/pwa/install.spec.ts).
 *
 * Other categories are NOT gated here — performance has its own
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
                // The CI boots via php artisan serve, which is slow — give
                // the audit longer wall-clock to avoid false-failing
                // waitForLoadState on the authenticated dashboard.
                maxWaitForLoad: 45_000,
                emulatedFormFactor: 'mobile',
                // Lighthouse 12 REMOVED the PWA category (and all its
                // audits: service-worker, installable-manifest, ...), so a
                // categories:pwa assertion can never run under @lhci/cli
                // >= 0.13 — it fails with "auditRan expected >= 1". The
                // PWA-specific guarantees live in the pwa-smoke Playwright
                // job instead (sw.spec.ts: SW activates + offline fallback;
                // install.spec.ts: manifest installability). This job keeps
                // a Lighthouse gate on the authenticated dashboard via
                // best-practices.
                onlyCategories: ['best-practices'],
            },
        },
        assert: {
            assertions: {
                // Gating: scored 0.93 on CI at re-scope time (LH 12.6.1),
                // so 0.9 holds the line without false-failing. Raise it if
                // the score climbs.
                'categories:best-practices': ['error', { minScore: 0.9 }],
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
