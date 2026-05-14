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

> The manual keyboard-only and screen-reader checklist is expanded in
> A11Y-DOC-2 (Phase-23, Phase 3). This section is the entry point.

### Automated

- `npm run lint` — static a11y lint (eslint-plugin-vuejs-accessibility).
- `npm run test:a11y` — axe-core smoke test against rendered pages.
- `php artisan test --filter=Phase23` — source-level a11y watchdogs.

### Manual (required for any new page, layout change, or new
interactive component)

- **Keyboard-only pass** — Tab through the whole flow: the skip-link
  works, focus is always visible, no keyboard trap, Escape closes every
  overlay, and focus returns sensibly on close.
- **Screen-reader pass** — NVDA (Windows) or VoiceOver (macOS): on a
  form, the label + required state + error are all announced on the
  field; on a table, the caption + column headers + sort state read
  correctly; after a save, the flash message is announced without a
  focus move.
