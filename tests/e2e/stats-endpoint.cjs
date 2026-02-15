const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    console.log('=== Step 1: Login Page ===');
    await page.goto('http://localhost:8001/login');
    await page.waitForLoadState('networkidle');
    console.log('Title:', await page.title());
    await page.screenshot({ path: 'e2e-screenshots/stats-01-login-page.png' });

    console.log('=== Step 2: Login ===');
    await page.getByLabel('Email').fill('test@example.com');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL('**/dashboard**', { timeout: 15000 }).catch(() => {});
    await page.waitForLoadState('networkidle');
    console.log('URL after login:', page.url());
    await page.screenshot({ path: 'e2e-screenshots/stats-02-dashboard.png' });

    const onDashboard = page.url().includes('dashboard') || page.url().includes('onboarding');
    if (!onDashboard) {
        console.log('Login may have failed. Current URL:', page.url());
        await page.screenshot({ path: 'e2e-screenshots/stats-02-login-failed.png' });
        await browser.close();
        console.log('\n=== E2E RESULTS ===');
        console.log('Login: FAIL (no test user seeded)');
        process.exit(1);
    }

    console.log('=== Step 3: Dashboard Content ===');
    const pageText = await page.textContent('body');
    const hasRevenue = pageText.includes('Monthly Revenue') || pageText.includes('revenue');
    const hasArrears = pageText.includes('Arrears') || pageText.includes('arrears');
    console.log('Has revenue metrics:', hasRevenue);
    console.log('Has arrears metrics:', hasArrears);

    console.log('=== Step 4: Stats Endpoint ===');
    const response = await page.evaluate(async () => {
        try {
            const resp = await fetch('/dashboard/stats');
            if (resp.headers.get('content-type')?.includes('json')) {
                return { status: resp.status, data: await resp.json() };
            }
            return { status: resp.status, error: 'Not JSON', text: (await resp.text()).substring(0, 200) };
        } catch (e) {
            return { error: e.message, status: 0 };
        }
    });
    console.log('Stats response status:', response.status);
    const hasFinancial = response.data && response.data.financial;
    const hasAging = response.data && response.data.arrears_aging;
    const hasActions = response.data && response.data.action_items;
    console.log('Has financial key:', Boolean(hasFinancial));
    console.log('Has arrears_aging key:', Boolean(hasAging));
    console.log('Has action_items key:', Boolean(hasActions));
    if (hasFinancial) {
        console.log('Financial keys:', Object.keys(response.data.financial));
    }
    if (hasActions) {
        console.log('Action items keys:', Object.keys(response.data.action_items));
    }
    if (response.error) {
        console.log('Error:', response.error);
    }
    await page.screenshot({ path: 'e2e-screenshots/stats-03-stats-fetched.png' });

    console.log('=== Step 5: Unauthenticated Access ===');
    const incogContext = await browser.newContext();
    const incogPage = await incogContext.newPage();
    const unauthResp = await incogPage.goto('http://localhost:8001/dashboard/stats');
    console.log('Unauthenticated status:', unauthResp.status());
    console.log('Redirected to login:', incogPage.url().includes('login'));
    await incogPage.screenshot({ path: 'e2e-screenshots/stats-04-unauth-redirect.png' });

    await browser.close();

    console.log('\n=== E2E RESULTS ===');
    console.log('Login: PASS');
    console.log('Dashboard loads:', onDashboard ? 'PASS' : 'FAIL');
    console.log('Stats endpoint:', response.status === 200 ? 'PASS' : 'FAIL (' + response.status + ')');
    console.log('JSON structure:', (hasFinancial && hasAging && hasActions) ? 'PASS' : 'FAIL');
    console.log('Auth protection:', incogPage.url().includes('login') ? 'PASS' : 'FAIL');

    process.exit((response.status === 200 && hasFinancial && hasAging && hasActions) ? 0 : 1);
})();
