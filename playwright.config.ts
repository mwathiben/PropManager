// Phase-23 A11Y-CI-2: Playwright config for the axe-core a11y smoke
// test. Intentionally minimal — this is NOT a general E2E harness, it
// is one spec that runs axe-core against the hot rendered pages. It
// expects an app already running at A11Y_BASE_URL (CI boots it the
// same way the load-smoke job does); it does not start its own
// server.
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/a11y',
    timeout: 45_000,
    fullyParallel: false,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: process.env.A11Y_BASE_URL || 'http://127.0.0.1:8000',
        trace: 'off',
        screenshot: 'off',
        video: 'off',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
