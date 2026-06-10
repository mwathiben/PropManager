// Phase-44 VISUAL-REGRESSION-2: snapshot 8 high-traffic pages under
// dir=rtl (Arabic locale). Catches silently-broken RTL layouts:
// absolute-positioned overlays still using `left:`, flex rows that
// missed `flex-row-reverse`, Tailwind LTR residue the Phase-44 RTL-
// MIGRATE-1 codemod missed. Snapshots live under __screenshots__/
// next to this spec; regenerate via `npm run test:rtl:update`.
//
// 8 pages chosen per Phase-36 ops dashboard traffic data (~80% of
// authenticated session time): Dashboard, Tenants/Index, Buildings/Index,
// Invoices/Index, Payments/Index (landlord side), Settings/Profile,
// plus unauth Login + Register.
import { test, expect, type Page } from '@playwright/test';

const EMAIL = process.env.A11Y_USER_EMAIL || 'loadtest@propmanager.test';
const PASSWORD = process.env.A11Y_USER_PASSWORD || 'password';

const AUTHED_PAGES: { label: string; path: string }[] = [
    { label: 'dashboard', path: '/dashboard' },
    { label: 'tenants-index', path: '/tenants' },
    { label: 'buildings-index', path: '/buildings' },
    { label: 'invoices-index', path: '/invoices' },
    { label: 'payments-index', path: '/payments' },
    { label: 'profile', path: '/profile' },
];

async function switchToArabic(page: Page): Promise<void> {
    // Phase-24 I18N-INFRA-4 endpoint: PATCH /locale {locale: 'ar'}.
    // Sets users.locale + session; SetLocale middleware applies on
    // the next request.
    //
    // Must run IN-PAGE (page.evaluate + fetch), not via page.request:
    // Playwright's APIRequestContext does not attach the app's Secure
    // session cookies over plain http, so the old page.request.patch
    // silently 419'd, the locale never switched, and every dir="rtl"
    // assertion failed against an English ltr page.
    const status = await page.evaluate(async () => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const res = await fetch('/locale', {
            method: 'PATCH',
            credentials: 'same-origin',
            redirect: 'manual',
            headers: {
                'X-XSRF-TOKEN': match ? decodeURIComponent(match[1]) : '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ locale: 'ar' }),
        });
        return res.status;
    });
    // 0 = opaqueredirect (back()'s 302 under redirect:manual) — success.
    // Anything 4xx/5xx means the switch silently failed and every
    // snapshot below would be LTR garbage; fail loudly here instead.
    expect([0, 200, 302], `locale switch must succeed (got HTTP ${status})`).toContain(status);
}

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.fill('#email', EMAIL);
    await page.fill('#password', PASSWORD);
    await page.getByRole('button', { name: /(Log in|تسجيل الدخول)/ }).click();
    await page.waitForURL('**/dashboard', { timeout: 15_000 });
}

test.describe('RTL visual snapshots', () => {
    test('unauthenticated login page renders RTL', async ({ page }) => {
        await page.goto('/login');
        // Force locale before snapshot — login is pre-auth so no
        // users.locale exists yet; the session cookie + Accept-Language
        // alone may not be enough.
        await page.evaluate(() => {
            document.documentElement.setAttribute('dir', 'rtl');
            document.documentElement.setAttribute('lang', 'ar');
        });
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveScreenshot('login-rtl.png', { fullPage: true });
    });

    test('unauthenticated register page renders RTL', async ({ page }) => {
        await page.goto('/register');
        await page.evaluate(() => {
            document.documentElement.setAttribute('dir', 'rtl');
            document.documentElement.setAttribute('lang', 'ar');
        });
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveScreenshot('register-rtl.png', { fullPage: true });
    });

    test('authenticated pages render RTL', async ({ page }) => {
        await login(page);
        await switchToArabic(page);

        for (const { label, path } of AUTHED_PAGES) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            const dir = await page.locator('html').getAttribute('dir');
            expect(dir, `${label} must render under dir="rtl" after locale switch`).toBe('rtl');
            await expect(page).toHaveScreenshot(`${label}-rtl.png`, { fullPage: true });
        }
    });
});
