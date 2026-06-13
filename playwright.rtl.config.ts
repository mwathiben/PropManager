// Phase-44 VISUAL-REGRESSION-1: RTL visual regression harness.
//
// Sibling to playwright.config.ts (Phase-23 axe-core a11y smoke).
// Pre-conditions: the SetLocale middleware honours the `locale` cookie,
// config('app.available_locales') contains 'ar' (Phase-44 Phase 1b),
// the Phase-43 LocaleHelper sets `dir="rtl"` on the <html> tag for ar.
//
// This config runs ONLY tests/a11y/rtl/. Snapshots live next to the
// spec under tests/a11y/rtl/__screenshots__/. CI gates on `toHaveScreenshot()`
// diff threshold; a regression that breaks an RTL layout (an absolute-
// positioned overlay still using `left:`, a flex row that didn't get
// flex-row-reverse, a Tailwind class the codemod missed) fails the run.
//
// Baseline refresh: `npm run test:rtl:update` (passes --update-snapshots).
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/a11y/rtl',
    // Keep baselines next to the spec under __screenshots__/ (as the runbook
    // and spec header document). Without this, Playwright defaults to the
    // `<spec>.spec.ts-snapshots/` sibling, which left __screenshots__/ holding
    // only a .gitkeep while the real baselines lived elsewhere — a confusing
    // split. The {projectName}/{platform} suffixes keep CI's Linux/Chromium
    // baselines from colliding with any locally-generated render.
    snapshotPathTemplate: '{testDir}/__screenshots__/{arg}-{projectName}-{platform}{ext}',
    timeout: 60_000,
    fullyParallel: false,
    retries: 0,
    reporter: 'list',
    expect: {
        toHaveScreenshot: {
            // 1% pixel diff threshold accommodates font-rendering
            // sub-pixel noise across Chromium versions. RTL layout
            // regressions (overflow, mirrored elements, absolute-
            // positioned widgets in wrong corner) blow this past 1%
            // immediately.
            maxDiffPixelRatio: 0.01,
        },
    },
    use: {
        baseURL: process.env.A11Y_BASE_URL || 'http://127.0.0.1:8000',
        trace: 'off',
        screenshot: 'off',
        video: 'off',
        locale: 'ar',
    },
    projects: [
        {
            name: 'chromium-rtl',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
            },
        },
    ],
});
