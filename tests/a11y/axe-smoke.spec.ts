// Phase-23 A11Y-CI-2: axe-core smoke test against rendered pages.
//
// Static lint (A11Y-CI-1) catches template-level issues but cannot
// catch RENDERED problems — computed contrast, focus order in the
// assembled DOM, ARIA that is only wrong in context, duplicate ids
// after composition. axe-core run against the actual pages is the
// complement.
//
// Gate: FAIL on `critical` violations only. `serious`/`moderate`/
// `minor` are reported to the run log but NOT gated — they are the
// baseline this test ratchets down over time (same shrink-only
// discipline as the eslint a11y baseline in eslint.config.js).
// Phase-23 fixed every `critical` on the scanned pages
// (button-name, select-name); the `serious` color-contrast backlog
// is tracked in docs/runbooks/accessibility.md and a later finding
// promotes `serious` into GATED_IMPACTS once it is cleared.
//
// Logs in as the seeded LoadTestSeeder landlord, same fixture the
// load-smoke job uses.
import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const EMAIL = process.env.A11Y_USER_EMAIL || 'loadtest@propmanager.test';
const PASSWORD = process.env.A11Y_USER_PASSWORD || 'password';

const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
const GATED_IMPACTS = new Set(['critical']);

// Authenticated hot paths — the same read paths the load-smoke job
// exercises, plus the buildings index.
const AUTHED_PAGES = ['/dashboard', '/invoices', '/tenants', '/buildings'];

interface AxeViolation {
    id: string;
    impact?: string | null;
    help: string;
    nodes: unknown[];
}

function gatedViolations(violations: AxeViolation[]): AxeViolation[] {
    return violations.filter((v) => GATED_IMPACTS.has(v.impact ?? ''));
}

function summarise(violations: AxeViolation[]): string {
    if (violations.length === 0) {
        return '  (none)';
    }
    return violations
        .map((v) => `  [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} node(s))`)
        .join('\n');
}

async function scan(page: Page, label: string): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
    const all = results.violations as AxeViolation[];
    const gated = gatedViolations(all);
    const ungated = all.filter((v) => !GATED_IMPACTS.has(v.impact ?? ''));

    // Report the non-gating baseline to the run log so regressions
    // are visible even though they do not fail the job.
    // eslint-disable-next-line no-console
    console.log(`\n[a11y] ${label} — baseline (non-gating):\n${summarise(ungated)}`);

    expect(gated, `${label} has CRITICAL a11y violations:\n${summarise(gated)}`).toEqual([]);
}

test.describe('axe-core a11y smoke', () => {
    test('login page', async ({ page }) => {
        await page.goto('/login');
        await scan(page, '/login');
    });

    test('authenticated hot pages', async ({ page }) => {
        await page.goto('/login');
        await page.fill('#email', EMAIL);
        await page.fill('#password', PASSWORD);
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL('**/dashboard', { timeout: 15_000 });

        for (const path of AUTHED_PAGES) {
            await page.goto(path);
            await scan(page, path);
        }
    });
});
