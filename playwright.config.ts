// Phase-23 A11Y-CI-2: Playwright config for the axe-core a11y smoke
// test. Intentionally minimal — this is NOT a general E2E harness, it
// is one spec that runs axe-core against the hot rendered pages. It
// expects an app already running at A11Y_BASE_URL (CI boots it the
// same way the load-smoke job does); it does not start its own
// server.
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/a11y',
    // The RTL visual-snapshot suite lives under tests/a11y/rtl but runs in
    // its OWN job/config (playwright.rtl.config.ts, project 'chromium-rtl').
    // Without this ignore the axe job re-runs the RTL spec under project
    // 'chromium', expecting a parallel *-chromium-linux.png baseline set
    // that was never seeded — failing the A11Y job on snapshots that are
    // not its responsibility.
    testIgnore: '**/rtl/**',
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
