// Phase-26 PWA-CI-2 / CI-3: Playwright config for the PWA smoke
// specs. Mirrors playwright.config.ts (the a11y config) but with
// testDir './tests/pwa'. Kept as a separate config file because the
// two suites have different baseURL env vars + projects so a single
// config gets messy.
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/pwa',
    timeout: 45_000,
    fullyParallel: false,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: process.env.PWA_BASE_URL || 'http://127.0.0.1:8000',
        trace: 'off',
        screenshot: 'off',
        video: 'off',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
