// Phase-23 A11Y-CI-1: static accessibility lint gate.
//
// The codebase had zero a11y tooling — a manual audit decays the
// moment a new component ships. eslint-plugin-vuejs-accessibility
// catches a large class of issues at lint time (missing alt, no
// label-for, click-without-keyboard, invalid aria-*, positive
// tabindex). This config is intentionally NARROW: it runs ONLY the
// a11y plugin's recommended ruleset against *.vue templates — it is
// not a general JS/TS style linter (Pint covers PHP; this repo has
// no prior JS lint baseline to honour).
//
// `npm run lint` is wired as a CI gate from this commit forward
// (blocking on PRs, like Pint). eslint exits non-zero only on
// `error`-level findings, so the BASELINED rules below are set to
// `warn`: the gate is green from day one and later Phase-23 findings
// ratchet each rule back to `error` as they fix its violations —
// the same shrink-only discipline as the Phase-18/19/22 watchdog
// baselines. Do NOT add new `warn` downgrades without a tracked
// note here; the direction is monotonic toward `error`.
import vueA11y from 'eslint-plugin-vuejs-accessibility';
import vueParser from 'vue-eslint-parser';
import tsParser from '@typescript-eslint/parser';

export default [
    {
        ignores: [
            'public/**',
            'vendor/**',
            'node_modules/**',
            'bootstrap/ssr/**',
            'storage/**',
        ],
    },
    {
        // The two `// eslint-disable-next-line no-console` directives
        // in app.js are legitimate (they guard DEV-only console calls)
        // but this a11y-only config does not run `no-console`, so
        // eslint would flag them as unused. Not an a11y signal —
        // silence it.
        linterOptions: {
            reportUnusedDisableDirectives: false,
        },
    },
    {
        files: ['resources/js/**/*.vue'],
        plugins: {
            'vuejs-accessibility': vueA11y,
        },
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                // <script lang="ts"> blocks need the TS parser; the
                // a11y rules only inspect the <template>, but
                // vue-eslint-parser still parses the script.
                parser: tsParser,
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
        },
        rules: {
            ...vueA11y.configs.recommended.rules,

            // --- Phase-23 A11Y-CI-1 baseline (shrink-only) ---------
            // Each rule below has pre-existing violations that a later
            // Phase-23 finding owns. Ratchet back to 'error' when that
            // finding lands.
            //
            // autofocus is used deliberately on auth + create forms;
            // revisit under the A11Y-DOC conformance review.
            'vuejs-accessibility/no-autofocus': 'warn',
            // label/control association — A11Y-FORM-2 (required-field)
            // + the broader form sweep tighten these.
            'vuejs-accessibility/label-has-for': 'warn',
            'vuejs-accessibility/form-control-has-label': 'warn',
            // div@click without keyboard handlers — A11Y-KBD-2
            // (Dropdown) + A11Y-KBD-3 (mobile sidebar) own the
            // interactive-element fixes.
            'vuejs-accessibility/no-static-element-interactions': 'warn',
            'vuejs-accessibility/click-events-have-key-events': 'warn',
            'vuejs-accessibility/mouse-events-have-key-events': 'warn',
            // single decorative-image alt gap — A11Y-SR-4 (map alt)
            // sweeps non-text alternatives.
            'vuejs-accessibility/alt-text': 'warn',
            // 7 icon-only <a> links lack an accessible name (icon child
            // only, no aria-label). Real WCAG 4.1.2 gap, but it is a
            // cross-page sweep of its own — not owned by any of the
            // four HIGH findings. Tracked for a follow-up ratchet.
            'vuejs-accessibility/anchor-has-content': 'warn',
            // 3 `<ul role="list">` sites. The rule calls role="list"
            // redundant, but it is the documented workaround for Safari
            // dropping list semantics when list-style:none is applied —
            // removing it blindly would REGRESS a11y. Kept at 'warn'
            // pending a per-site check that the workaround still
            // applies.
            'vuejs-accessibility/no-redundant-roles': 'warn',
        },
    },
];
