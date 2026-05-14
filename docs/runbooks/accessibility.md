# Accessibility Conformance Statement

PropManager targets **WCAG 2.1 Level AA**. This is a VPAT-lite: each
in-scope AA success criterion is mapped to a conformance status with a
note pointing at the implementing component or audit finding. It is
honest, not aspirational — "Partially Supports" with a real note beats
a fake "Supports".

Shipped by the Phase-23 [A11Y] audit cycle (2026-05-14) on top of the
Phase-20 a11y foundations (Badge AA contrast, Modal focus trap,
InputError `role="alert"`, semantic layout landmarks).

The accessibility testing process — automated gates plus the manual
keyboard / screen-reader checklist — is documented in the **Testing**
section below.

## Conformance status

| Criterion | Level | Status | Notes |
|-----------|-------|--------|-------|
| 1.1.1 Non-text Content | A | Partially Supports | Decorative icons are `aria-hidden`; icon-only `<button>`s use `IconButton` (required `ariaLabel`). Gaps: ~7 icon-only `<a>` links lack an accessible name (baselined in `eslint.config.js`, `vuejs-accessibility/anchor-has-content`); the Leaflet `BuildingMap` text alternative is tracked as A11Y-SR-4. |
| 1.3.1 Info and Relationships | A | Supports | Form errors wired via `aria-describedby`/`aria-invalid` (A11Y-FORM-1); required fields marked (A11Y-FORM-2); `DataTable`/`VirtualDataTable` carry `<caption>`, `scope="col"`, `aria-sort` (A11Y-SR-3); layout uses real `<header>`/`<nav>`/`<main>`/`<aside>` landmarks with distinct `aria-label`s (A11Y-SR-2); every page contributes an `<h1>` to the outline (A11Y-SR-2). |
| 1.4.1 Use of Color | A | Supports | Status is never colour-only: every `*StatusBadge` pairs colour with a text label, `KycBadge` adds an icon, `InputError` pairs red with an icon (A11Y-VISUAL-2). The index-page status cells were audited — all route through the badge components; no ad-hoc coloured-dot/text cells found. |
| 1.4.3 Contrast (Minimum) | AA | Supports | `Badge` colour pairs hardened to AA in Phase 20 (`bg-{c}-100` / `text-{c}-900`); `InputError` uses `text-red-700` (5.0:1). |
| 1.4.11 Non-text Contrast | AA | Partially Supports | Focus rings and badge borders meet 3:1. A full non-text-contrast sweep of every interactive control is not yet exhaustive. |
| 1.4.13 Content on Hover/Focus | AA | Partially Supports | The `Dropdown` menu is dismissable (Escape) and the trigger stays hovered. A general audit of all hover-revealed content is not yet exhaustive. |
| 2.1.1 Keyboard | A | Supports | `Dropdown` moves focus into the menu, supports Up/Down/Home/End, and Escape restores focus to the trigger (A11Y-KBD-2). Sortable table headers are real `<button>`s (A11Y-SR-3). |
| 2.1.2 No Keyboard Trap | A | Supports | `Modal` (Phase 20) and the mobile sidebar overlay (A11Y-KBD-3) trap focus *and* are dismissable with Escape; focus returns to the opener on close. |
| 2.3.3 Animation from Interactions | AAA | Supports | Global `@media (prefers-reduced-motion: reduce)` reset neutralises transition/animation durations (A11Y-VISUAL-1). Tracked above AA because it is cheap and high-value. |
| 2.4.1 Bypass Blocks | A | Supports | Skip-link to `#main-content` is the first focusable element in `AuthenticatedLayout` (A11Y-KBD-1). `GuestLayout` is exempt — its pages are short single-form layouts with no repeated nav block to bypass. |
| 2.4.3 Focus Order | A | Supports | Focus traps in `Modal` + mobile sidebar; `Dropdown` moves focus predictably. |
| 2.4.6 Headings and Labels | A | Supports | One `<h1>` per page (A11Y-SR-2); form labels associated via `for`/`id`; `<nav>` landmarks have distinct accessible names. |
| 2.4.7 Focus Visible | AA | Partially Supports | `focus:ring-2 focus:ring-offset-2` is broadly applied. A consistency sweep for any `focus:outline-none` without a visible replacement is tracked as A11Y-VISUAL-3. |
| 3.2.3 Consistent Navigation | AA | Supports | Navigation is rendered once from a shared `navigationItems` definition in `AuthenticatedLayout`. |
| 3.3.1 Error Identification | A | Supports | Server validation errors render in `InputError` (`role="alert"`) and are programmatically associated to their input (A11Y-FORM-1). |
| 3.3.2 Labels or Instructions | A | Supports | Required fields carry a visible asterisk + sr-only " (required)" text (A11Y-FORM-2); helper-text association is tracked as A11Y-FORM-3. |
| 4.1.2 Name, Role, Value | A | Supports | `aria-invalid` on errored inputs; `role="dialog"` + `aria-modal` on the mobile sidebar; `aria-expanded`/`aria-controls` on the hamburger; `aria-sort` on sortable headers. |
| 4.1.3 Status Messages | AA | Supports | A polite/assertive `LiveAnnouncer` region pair is mounted once per layout and wired to Inertia flash messages (A11Y-SR-1). `useAnnouncer()` is exported for feature code to announce async status. |

## Known gaps

These criteria are **Partially Supports** and have tracked follow-up:

- **1.1.1** — ~7 icon-only `<a>` links need an accessible name; the
  `BuildingMap` Leaflet component needs a text alternative (A11Y-SR-4).
- **1.4.11 / 1.4.13** — non-text-contrast and hover/focus-content
  audits are not yet exhaustive.
- **2.4.7** — `focus:outline-none`-without-replacement consistency
  sweep is tracked as A11Y-VISUAL-3.
- **Helper-text association (1.3.1 tail)** — tracked as A11Y-FORM-3.

Criteria that need real assistive-technology testing (full NVDA /
VoiceOver passes) are covered by the manual checklist in the Testing
section rather than claimed from static review alone.

## Maintenance commitment

The conformance above does not decay silently:

- **Static lint gate** — `eslint-plugin-vuejs-accessibility` runs as a
  blocking CI step (`npm run lint`). Baselined rules are listed in
  `eslint.config.js` and ratchet toward `error` as follow-up findings
  land.
- **Source-level watchdogs** — `tests/Feature/Accessibility/Phase23*Test.php`
  pin the structural wins (skip-link present, live announcer mounted,
  nav landmarks labelled, table semantics, reduced-motion rule, etc.).
- **axe-core smoke** — runs against rendered pages in CI (A11Y-CI-2).

## Testing

Automation catches roughly half of WCAG issues; the rest needs a
human with a keyboard and a screen reader. Both halves are documented
here so the manual pass actually happens consistently.

### Automated

Run locally before pushing a11y-affecting changes:

- `npm run lint` — static a11y lint (eslint-plugin-vuejs-accessibility).
  Reads the `eslint.config.js` baseline; a new `error`-level finding
  fails. Catches missing alt, label/control association, invalid
  `aria-*`, click-without-keyboard.
- `npm run test:a11y` — axe-core smoke (Playwright) against the login
  page + the hot authenticated pages. Fails on `critical` violations;
  `serious`/`moderate` are printed to the run log as the shrink-only
  baseline. Needs the app running locally — see
  `playwright.config.ts` for `A11Y_BASE_URL`.
- `php artisan test --filter=Phase23` — the source-level watchdog
  suite (`tests/Feature/Accessibility/Phase23*Test.php`).

All three also run in CI (`npm run lint` blocks; `a11y-smoke` blocks
on PR, warns on push).

### Manual — keyboard-only pass

Unplug the mouse. Tab through the whole flow and confirm:

- The **skip-link** appears on the first Tab and jumps focus to the
  main content.
- Focus is **always visible** — every interactive element shows a
  ring/border when focused; focus never "disappears".
- **Tab order** follows reading order; no positive `tabindex`.
- **No keyboard trap** — you can always Tab back out of a widget.
- **Escape** closes every overlay (Modal, Dropdown, mobile sidebar)
  and focus returns to the element that opened it.
- **Dropdowns** — focus moves into the menu on open; Up/Down/Home/End
  move between items.
- Every action reachable by mouse is reachable by keyboard (Enter /
  Space activate buttons).

### Manual — screen-reader pass

NVDA on Windows, VoiceOver on macOS (`Cmd+F5`). Confirm:

- **Forms** — tabbing onto a field announces the label, the required
  state, and (when present) the helper text; submitting an invalid
  form announces the error on the field, not just visually.
- **Tables** — the DataTable announces its caption, column headers
  read as you move across cells, and a sortable column announces its
  sort state.
- **Flash messages** — after a save/redirect, the success or error
  message is announced by the live region without a focus move.
- **Landmarks** — the landmark list names each region (Primary nav,
  Mobile primary nav, Breadcrumb, main, banner).
- **Headings** — every page has exactly one `<h1>` and the heading
  outline is sensible.
- **Icons** — decorative icons are silent; icon-only buttons announce
  a meaningful name.

### When a manual pass is required

- Any **new page** (or a page that gained a new interactive section).
- Any **layout change** (`AuthenticatedLayout`, `GuestLayout`).
- Any **new interactive component** (modal, dropdown, custom control).
- Before promoting the `a11y-smoke` gate to also fail on `serious`.
