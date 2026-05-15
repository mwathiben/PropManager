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
