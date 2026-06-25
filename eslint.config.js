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

// Phase-44 ESLINT-CUSTOM-1: in-IDE flag for hardcoded English in Vue
// templates. Complements the Phase-43 HardcodedEnglishScanner PHP-side
// ratchet — devs see the violation while typing, not at PR time.
// Heuristic only; relies on $t() / aria-label / route() to be the
// dominant way English appears in templates.
const LTR_CLASS_PATTERN = /(?:^|\s)(ml|mr|pl|pr|left|right|border-l|border-r|rounded-l|rounded-r|text-left|text-right)-/;
const HARDCODED_ENGLISH_PATTERN = /[A-Za-z][A-Za-z\s,'.!?:;-]{4,}/;

const propManagerPlugin = {
    rules: {
        'no-hardcoded-english-strings': {
            meta: {
                type: 'suggestion',
                docs: { description: 'Disallow literal English strings in Vue templates (use $t()).' },
                schema: [],
                messages: {
                    hardcoded: 'Hardcoded English "{{ text }}" — wrap in $t() for i18n.',
                },
            },
            create(context) {
                const services = context.sourceCode.parserServices || context.parserServices;
                if (!services || !services.defineTemplateBodyVisitor) return {};
                return services.defineTemplateBodyVisitor({
                    VText(node) {
                        const text = (node.value || '').trim();
                        if (!text) return;
                        if (text.startsWith('{{') || text.startsWith('//')) return;
                        if (HARDCODED_ENGLISH_PATTERN.test(text)) {
                            context.report({
                                node,
                                messageId: 'hardcoded',
                                data: { text: text.slice(0, 40) },
                            });
                        }
                    },
                });
            },
        },
        'no-ltr-class': {
            meta: {
                type: 'problem',
                docs: { description: 'Disallow LTR-only Tailwind classes; use logical equivalents.' },
                schema: [],
                messages: {
                    ltr: 'LTR-only Tailwind class "{{ cls }}-" — use logical (ms-/me-/ps-/pe-/start-/end-/text-start/text-end).',
                },
            },
            create(context) {
                const services = context.sourceCode.parserServices || context.parserServices;
                if (!services || !services.defineTemplateBodyVisitor) return {};
                return services.defineTemplateBodyVisitor({
                    VAttribute(node) {
                        const keyName = node.key && node.key.name
                            ? (node.key.name.name || node.key.name)
                            : null;
                        if (keyName !== 'class') return;
                        const raw = node.value && node.value.value ? node.value.value : '';
                        const match = raw.match(LTR_CLASS_PATTERN);
                        if (match) {
                            context.report({
                                node: node.value,
                                messageId: 'ltr',
                                data: { cls: match[1] },
                            });
                        }
                    },
                });
            },
        },
    },
};

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
            'propmanager': propManagerPlugin,
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
            //
            // A11Y-PAYDOWN-1: `some` (associated by for/id OR nesting) is the
            // WCAG 1.3.1 / HTML-spec standard — either method is a valid,
            // screen-reader-recognised association. The plugin default `every`
            // (BOTH nesting AND for/id) over-flags 260 already-accessible
            // labels as false positives. `some` flags only genuinely
            // unassociated labels — the real debt the baseline now ratchets
            // down, fixable by adding for/id without restructuring the DOM.
            'vuejs-accessibility/label-has-for': ['warn', { required: { some: ['nesting', 'id'] } }],
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

            // --- Phase-44 ESLINT-CUSTOM (shrink-only) -----------------
            // Phase-53 ESLINT-RATCHET-2: severity stays at 'warn' so
            // `npm run lint` keeps a passable exit code for the dev
            // workflow, but the shrink-only baseline in
            // .eslint-baseline.json + scripts/lint-baseline.mjs (CI-
            // wired via `npm run lint:baseline`) treats any growth
            // past baseline as a hard failure. Lower the baseline as
            // commits fix real violations; never raise. New violations
            // surface in IDE as warns AND fail CI via the baseline
            // gate, which is the meaningful contract.
            'propmanager/no-hardcoded-english-strings': 'warn',
            'propmanager/no-ltr-class': 'warn',
        },
    },

    // A11Y-PAYDOWN-INTERACTION: documented per-file resolutions where the rule
    // is a deliberate UX policy or a genuine false positive. Inline
    // eslint-disable comments are NOT honoured by vue-eslint-parser in this
    // flat config, so these are scoped here instead — each with its reason.
    {
        // Deliberate sole-primary-field autofocus on auth / 2FA / profile entry
        // forms (good UX on single-purpose pages; the team's documented policy).
        files: [
            '**/Auth/ConfirmPassword.vue', '**/Auth/ForgotPassword.vue', '**/Auth/Login.vue',
            '**/Auth/Register.vue', '**/Auth/ResetPassword.vue', '**/Auth/TwoFactorChallenge.vue',
            '**/Settings/TwoFactor.vue', '**/Settings/TwoFactorRecoveryCodes.vue', '**/Settings/TwoFactorSetup.vue',
            '**/Profile/Partials/UpdateProfileInformationForm.vue',
        ],
        rules: { 'vuejs-accessibility/no-autofocus': 'off' },
    },
    {
        // False positive: <label> is natively interactive (HTML 4.10.18) and the
        // drag-and-drop containers wrap interactive children, so role="button"
        // would be semantically wrong / invalid nested-interactive HTML.
        files: [
            '**/Pages/Dashboard.vue', '**/Finances/Payments/BulkImport.vue',
            '**/Tenant/CompleteKyc.vue', '**/Components/Inbox/InitiateThreadDialog.vue',
        ],
        rules: { 'vuejs-accessibility/no-static-element-interactions': 'off' },
    },
    {
        // False positive: Laravel paginator anchors render link.label (incl. HTML
        // entities for prev/next) at runtime; eslint cannot see the content.
        files: ['**/Pages/Water/tabs/ReviewTab.vue', '**/Pages/Imports/Index.vue'],
        rules: { 'vuejs-accessibility/anchor-has-content': 'off' },
    },
    {
        // Intentional role="list" — the documented Safari workaround (VoiceOver
        // drops list semantics under list-style:none). Keep it; the rule's
        // "redundant" call does not apply on these lists.
        files: [
            '**/Components/TicketActivityTimeline.vue', '**/Pages/ActivityLogs/Index.vue', '**/Pages/Tenants/Show.vue',
        ],
        rules: { 'vuejs-accessibility/no-redundant-roles': 'off' },
    },
];
