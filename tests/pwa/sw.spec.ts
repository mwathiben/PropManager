// Phase-26 PWA-CI-2: service-worker integration test.
//
// Lighthouse PWA category (PWA-CI-1) checks the SW REGISTERS. It does
// NOT check that the SW handles a real navigation correctly when the
// network is down. This spec is the complement: it logs in, asserts
// the SW becomes the controller, then forces context.setOffline(true)
// and asserts the offline page renders (not the browser default
// offline screen).
//
// Logged in as the seeded LoadTestSeeder landlord (same fixture as
// the axe-smoke + load-smoke jobs).
import { test, expect } from '@playwright/test';

const EMAIL = process.env.PWA_USER_EMAIL || 'loadtest@propmanager.test';
const PASSWORD = process.env.PWA_USER_PASSWORD || 'password';

async function login(page: import('@playwright/test').Page): Promise<void> {
    await page.goto('/login');
    await page.fill('#email', EMAIL);
    await page.fill('#password', PASSWORD);
    await page.getByRole('button', { name: 'Log in' }).click();
    // The authenticated dashboard pulls ~50 code-split chunks; under the
    // CI's `php artisan serve` (no opcache, no HTTP/2) that first load is
    // slow even with PHP_CLI_SERVER_WORKERS, so the login redirect needs
    // generous headroom. Production (HTTP/2 + opcache + CDN) loads in <1s.
    await page.waitForURL('**/dashboard', { timeout: 60_000 });
}

test.describe('PWA service worker integration', () => {
    test('SW registers and takes control on /dashboard', async ({ page }) => {
        await login(page);

        // Wait for the SW to become the controller. The install precaches
        // ~469 build assets then skipWaiting + clientsClaim, which is async
        // and slow under the CI's `php artisan serve`, so allow headroom.
        const controller = await page.waitForFunction(
            () => navigator.serviceWorker.controller !== null,
            null,
            { timeout: 60_000 },
        );

        expect(controller).toBeTruthy();

        const scope = await page.evaluate(() =>
            navigator.serviceWorker.controller?.scriptURL,
        );
        expect(scope, 'SW must register at /sw.js (Laravel route proxies to public/build/sw.js with Service-Worker-Allowed: /)')
            .toMatch(/\/sw\.js$/);
    });

    test('navigation fallback serves /offline when network is down', async ({ page, context }) => {
        await login(page);

        // Wait for the SW to take control before going offline — if
        // setOffline races the registration, Chrome serves its own
        // default offline page.
        await page.waitForFunction(
            () => navigator.serviceWorker.controller !== null,
            null,
            { timeout: 60_000 },
        );

        await context.setOffline(true);

        // Navigate to a page that the SW can't reach from cache —
        // /tenants is unlikely to have been precached unless explicitly
        // visited. Workbox's NavigationRoute fallback should serve
        // /offline.
        await page.goto('/tenants', { waitUntil: 'domcontentloaded' });

        // The /offline shell (resources/js/Pages/Offline.vue) renders a
        // [data-testid="offline-page"] root — assert that rather than copy
        // text, which is i18n'd (t('offline.heading')) and would couple this
        // test to the active translation migration.
        await expect(page.getByTestId('offline-page')).toBeVisible({ timeout: 10_000 });

        await context.setOffline(false);
    });
});
