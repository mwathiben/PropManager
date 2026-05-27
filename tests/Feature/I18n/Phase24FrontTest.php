<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-24 I18N-FRONT-1 watchdog — the vue-i18n engine is installed
 * and wired in app.js, hydrated from the Inertia-shared props.
 */
class Phase24FrontTest extends TestCase
{
    public function test_vue_i18n_is_a_dependency(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true);

        $this->assertArrayHasKey(
            'vue-i18n',
            $package['dependencies'] ?? [],
            'I18N-FRONT-1: vue-i18n must be a runtime dependency.',
        );
    }

    public function test_use_i18n_composable_exists(): void
    {
        $path = resource_path('js/composables/useI18n.ts');
        $this->assertFileExists($path, 'I18N-FRONT-2: useI18n.ts must exist.');

        $contents = file_get_contents($path);
        $this->assertStringContainsString(
            'export function useI18n',
            $contents,
            'I18N-FRONT-2: useI18n must be exported as a composable.',
        );
        $this->assertStringContainsString(
            'availableLocales',
            $contents,
            'I18N-FRONT-2: useI18n must expose the supported-locale list.',
        );
    }

    public function test_locale_selector_is_mounted_in_personal_info(): void
    {
        $selectorPath = resource_path('js/Components/LocaleSelector.vue');
        $this->assertFileExists($selectorPath, 'I18N-FRONT-2: LocaleSelector.vue must exist.');

        $selector = file_get_contents($selectorPath);
        $this->assertStringContainsString(
            "router.patch(route('locale.update')",
            $selector,
            'I18N-FRONT-2: LocaleSelector must PATCH the locale.update endpoint.',
        );

        $tab = file_get_contents(resource_path('js/Pages/Profile/Partials/PersonalInfoTab.vue'));
        $this->assertStringContainsString(
            'LocaleSelector',
            $tab,
            'I18N-FRONT-2: LocaleSelector must be mounted in the Profile > PersonalInfo tab.',
        );
    }

    public function test_chrome_files_use_translation_keys(): void
    {
        $chromeFiles = [
            'js/Layouts/AuthenticatedLayout.vue',
            'js/Layouts/GuestLayout.vue',
            'js/Pages/Auth/Login.vue',
            'js/Pages/Auth/ForgotPassword.vue',
            'js/Pages/Auth/Register.vue',
            'js/Pages/Auth/ResetPassword.vue',
            'js/Pages/Auth/ConfirmPassword.vue',
            'js/Pages/Auth/VerifyEmail.vue',
            'js/Pages/Auth/TwoFactorChallenge.vue',
        ];

        foreach ($chromeFiles as $rel) {
            $contents = file_get_contents(resource_path($rel));
            $this->assertStringContainsString(
                "from '@/composables/useI18n'",
                $contents,
                "I18N-FRONT-3: {$rel} must import useI18n.",
            );
            $this->assertMatchesRegularExpression(
                "/t\\(['\"][a-z._]+['\"]\\)/",
                $contents,
                "I18N-FRONT-3: {$rel} must use t('...') translation calls.",
            );
        }
    }

    public function test_register_role_help_strings_are_translation_keys(): void
    {
        // The Register page role-helper block uses role-specific
        // copy that's a frequent contributor footgun: the easiest way
        // to break i18n is to add a new role here and forget the key.
        //
        // 2026-05-27: keyspace moved from the legacy `auth.register.*`
        // JSON catalog to a dedicated `auth_register.*` PHP namespace
        // (lang/{en,sw,ar}/auth_register.php) — substring updated in
        // lockstep with Register.vue.
        $contents = file_get_contents(resource_path('js/Pages/Auth/Register.vue'));

        foreach (['role_landlord_body', 'role_caretaker_body', 'role_tenant_body'] as $key) {
            $this->assertStringContainsString(
                "auth_register.{$key}",
                $contents,
                "I18N-FRONT-3: Register must resolve auth_register.{$key} via t().",
            );
        }
    }

    public function test_date_inputs_are_native_or_locale_aware(): void
    {
        // I18N-FRONT-4: PropManager uses the native browser
        // <input type="date"> exclusively — no custom date picker
        // exists. Native inputs are locale-aware out of the box
        // (the browser uses the OS/document locale for the month
        // names + date order). The watchdog enforces this: if a
        // future contributor adds a custom date-picker wrapper, the
        // test fails and forces the contributor to route locale
        // through useFormatters (Phase-24 I18N-FORMAT-1).
        $componentsRoot = resource_path('js/Components');

        $candidates = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($componentsRoot));
        foreach ($iter as $file) {
            $name = $file->getFilename();
            if (preg_match('/Date(Picker|Range|Input)\.vue$/i', $name)) {
                $candidates[] = $file->getPathname();
            }
        }

        if ($candidates === []) {
            $this->assertTrue(true, 'No custom date pickers — native <input type="date"> is browser-localised, locale handling is correct by default.');

            return;
        }

        foreach ($candidates as $path) {
            $contents = file_get_contents($path);
            $this->assertStringContainsString(
                '@/composables/useFormatters',
                $contents,
                sprintf(
                    'I18N-FRONT-4: %s is a custom date component — it MUST use useFormatters (which is locale-aware via I18N-FORMAT-1).',
                    basename($path),
                ),
            );
        }
    }

    public function test_chrome_bundle_keyspace_present_in_both_locales(): void
    {
        // The chrome key set must exist in BOTH locales — a Swahili
        // user landing on /login or /dashboard cannot fall back to
        // an English-only key without a flash of untranslated text.
        $required = [
            'common.email',
            'common.password',
            'nav.dashboard',
            'nav.settings',
            'nav.skip_to_main',
            'menu.log_out',
            'menu.account',
            'role.landlord',
            'role.tenant',
            'auth.login.title',
            'auth.login.submit',
            // auth.register.* keyspace lives in the dedicated
            // lang/{locale}/auth_register.php PHP namespace as of
            // 2026-05-27 — removed from chrome-bundle required list.
            'auth.forgot.title',
            'auth.reset.title',
            'auth.confirm.title',
            'auth.verify.title',
            'auth.tfa.title',
            'banner.viewing_as',
            'banner.restricted_title',
            'empty.default_title',
        ];

        foreach (array_keys(config('app.available_locales')) as $locale) {
            $bundle = json_decode(file_get_contents(lang_path("{$locale}.json")), true) ?: [];

            foreach ($required as $key) {
                $this->assertNotSame(
                    null,
                    data_get($bundle, $key),
                    "I18N-FRONT-3 / I18N-SWAHILI-2: lang/{$locale}.json must define '{$key}'.",
                );
            }
        }
    }

    public function test_app_js_wires_vue_i18n(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "import { createI18n } from 'vue-i18n'",
            $appJs,
            'I18N-FRONT-1: app.js must import createI18n.',
        );
        $this->assertStringContainsString(
            'createI18n(',
            $appJs,
            'I18N-FRONT-1: app.js must instantiate the i18n engine.',
        );
        $this->assertStringContainsString(
            '.use(i18n)',
            $appJs,
            'I18N-FRONT-1: the i18n plugin must be registered on the app.',
        );
        // Hydrated from the Inertia-shared props, not a hardcoded locale.
        $this->assertStringContainsString(
            'props.initialPage?.props?.locale',
            $appJs,
            'I18N-FRONT-1: the locale must come from the Inertia-shared props.',
        );
        $this->assertStringContainsString(
            'props.initialPage?.props?.i18n',
            $appJs,
            'I18N-FRONT-1: the message bundle must come from the Inertia-shared props.',
        );
    }
}
