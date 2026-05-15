/**
 * Phase-26 PWA-CI-1: Lighthouse CI puppeteer login hook.
 *
 * Lighthouse runs Chrome via Puppeteer. This script logs in BEFORE
 * the audit so the audited /dashboard page is the authenticated
 * landlord dashboard, not the redirect-to-login.
 *
 * Reads credentials from env (matching the a11y-smoke job pattern in
 * .github/workflows/ci.yml). The seeded LoadTestSeeder landlord is
 * the canonical CI fixture.
 */

module.exports = async (browser, context) => {
    const email = process.env.LHCI_USER_EMAIL || 'loadtest@propmanager.test';
    const password = process.env.LHCI_USER_PASSWORD || 'password';
    const baseUrl = (context && context.url) ? new URL(context.url).origin : (process.env.LHCI_BASE_URL || 'http://127.0.0.1:8000');

    const page = await browser.newPage();
    try {
        await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle2', timeout: 30_000 });
        await page.type('#email', email);
        await page.type('#password', password);

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30_000 }),
            page.click('button[type="submit"]'),
        ]);
    } finally {
        await page.close();
    }
};
