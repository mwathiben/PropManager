# RTL visual snapshots — seeding + regression workflow

**Owner**: Phase 44 [I18N-RTL] + Phase 53 [DEFER-CLEANUP-3]
**Suite**: `tests/a11y/rtl/rtl-snapshot.spec.ts` (8 pages)
**Config**: `playwright.rtl.config.ts`
**Baselines**: `tests/a11y/rtl/__screenshots__/`
**npm scripts**: `npm run test:rtl` (verify) / `npm run test:rtl:update` (regenerate)

---

## What this suite catches

The codemod in Phase 44 RTL-MIGRATE-1 swapped LTR-leaning Tailwind classes (`ml-`, `pl-`, `text-left`, etc.) for logical-property variants (`ms-`, `ps-`, `text-start`). The codemod can't see CSS-in-JS, custom Vue styles, absolute-positioned overlays that still reference `left:`, or flex rows that need `flex-row-reverse`. The Playwright RTL snapshots catch these by rendering 8 high-traffic pages in Arabic and comparing each render to a committed baseline.

`maxDiffPixelRatio` is set to `0.01` (1%) in `playwright.rtl.config.ts`. That accommodates sub-pixel font-rendering noise across Chromium versions while still failing hard on real layout regressions (a 50-pixel overlay in the wrong corner is far past 1%).

---

## When to regenerate baselines

**Only when the visual change is intentional.** Examples:

- A deliberate UI redesign on one of the 8 covered pages.
- A Tailwind upgrade that shifts default spacing values.
- A Vue component refactor whose markup output legitimately differs but renders identically per the design.

**Never** regenerate baselines to "make the test pass" without understanding what changed. A diff means either (a) intentional design change → regenerate baseline AND mention in the PR description, or (b) regression → fix the regression. The default is (b).

---

## Seeding recipe (first-time, after Phase 44 ship)

Phase 44 shipped the suite and Phase 53 wires it into CI, but the initial baseline `.png` files are operator-seeded the first time the suite passes locally against a deliberate, known-good state. Run this once and commit the result.

1. **Boot a fresh dev environment** with a known-good seed.
   ```bash
   php artisan migrate:fresh --seed
   php artisan db:seed --class=Phase22LoadTestSeeder   # creates loadtest@propmanager.test
   php artisan serve --port=8000 &
   npm run dev &
   ```
2. **Install Chromium** (one-time).
   ```bash
   npx playwright install --with-deps chromium
   ```
3. **Run the suite with snapshot update.**
   ```bash
   A11Y_BASE_URL=http://127.0.0.1:8000 \
   A11Y_USER_EMAIL=loadtest@propmanager.test \
   A11Y_USER_PASSWORD=password \
   npm run test:rtl:update
   ```
4. **Commit the generated baselines.**
   ```bash
   git add tests/a11y/rtl/__screenshots__/
   git commit -m "test(rtl): seed Phase-44 visual snapshot baselines"
   ```

---

## Regression investigation steps

A failing snapshot test produces a diff image at `test-results/<test-name>/`. Open the three artifacts side-by-side:

1. `expected.png` — committed baseline.
2. `actual.png` — current render.
3. `diff.png` — overlay highlighting the pixel deltas.

Then:

- If the diff is a deliberate UI change → regenerate baseline (see above) AND call out the intent in the PR description.
- If the diff is unexpected → walk the rendered DOM for the failing page in DevTools with `dir="rtl"` set on `<html>` and look for:
  - **Absolute-positioned overlays** still using `left:` / `right:` instead of `inset-inline-start:` / `inset-inline-end:`.
  - **Tailwind LTR residue** the codemod missed — search the page's class attribute for `ml-`, `mr-`, `pl-`, `pr-`, `left-`, `right-`, `text-left`, `text-right`. The `propmanager/no-ltr-class` ESLint rule (Phase 53 ESLINT-RATCHET) catches new instances at lint time; an escape may be in CSS-in-JS or a dynamic class binding.
  - **Flex direction** — RTL flex rows usually need `flex-row-reverse` OR a logical-direction alternative.

---

## maxDiffPixelRatio policy

`maxDiffPixelRatio: 0.01` is calibrated for Chromium-only execution. Cross-browser variance (Firefox/Safari sub-pixel rendering) exceeds 1% routinely; don't add other browsers without first raising the threshold or pinning a single-browser baseline-per-browser folder.

If a known fragile area (e.g., a chart that emits sub-pixel anti-aliased lines) blows past 1% routinely, prefer a `mask:` on the locator over raising the global threshold.

---

## CI integration (Phase 53 RTL-BASELINES-2)

`.github/workflows/ci.yml` runs `npm run test:rtl` on every PR after the operator has seeded the baseline. The workflow:

1. Boots the same dev environment the seeding recipe uses.
2. Installs Chromium via `npx playwright install --with-deps chromium`.
3. Runs `npm run test:rtl` (no `--update-snapshots` flag — strict compare against committed baselines).
4. Uploads `test-results/` as an artifact on failure so the PR author can inspect the diff without re-running locally.

If the baseline directory is empty (operator hasn't seeded yet), the suite still passes — Playwright's `toHaveScreenshot()` writes the baseline on first run when none exists. That keeps CI green during the bootstrap window.
