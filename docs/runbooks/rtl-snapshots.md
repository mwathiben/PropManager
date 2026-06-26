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

## Generate baselines on Linux, NOT on a dev box (read this first)

`toHaveScreenshot()` is pixel-sensitive to the OS + font stack that produced the
image. The CI `rtl-smoke` job runs on `ubuntu-latest` with Chromium installed via
`npx playwright install --with-deps chromium`. A baseline generated on Windows or
macOS renders fonts differently and blows past the 1% `maxDiffPixelRatio` on
**every** page — so it can never go green in CI.

**Therefore baselines MUST be generated on a Linux runner.** Do not run
`npm run test:rtl:update` on a laptop and commit the result. Use the dedicated
regen workflow, which mirrors the CI job exactly:

1. Push your branch (with any deliberate UI change + the seeded fixture state).
2. `gh workflow run rtl-baseline-regen.yml --ref <your-branch>`
3. `gh run download <run-id> --name rtl-baselines --dir tests/a11y/rtl/__screenshots__/`
4. **Eyeball every PNG** (confirm each is a correct RTL render, not a blank/error page).
5. `git add tests/a11y/rtl/__screenshots__/ && git commit -m "test(rtl): regenerate visual baselines"`
6. Push; the PR's `rtl-smoke` job now compares against the fresh Linux baselines.

The fixture must be **deterministic**: `LoadTestSeeder` anchors all invoice/lease
dates to a fixed reference (NOT `now()`) and `InvoiceController@index` has a stable
`id` tiebreaker, so the rendered rows don't drift day-to-day or reorder run-to-run.
If you add a covered page that renders time-relative data, freeze it the same way
or the baseline will rot.

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

**There is no self-healing bootstrap.** A missing baseline does NOT pass — Playwright's
`toHaveScreenshot()` writes the new file *and fails the run* the first time a baseline is
absent. So the `__screenshots__/` directory must contain a committed `.png` for every page
the suite snapshots, generated on Linux per the recipe above, before `rtl-smoke` can go green.
