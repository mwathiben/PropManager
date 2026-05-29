<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-44 CI-1: consolidated I18N + RTL surface watchdog.
 *
 * Per-phase tests (Phase24CiTest, Phase43*, Phase44*) cover individual
 * surfaces. This is the integration assertion: the Phase 44 cycle's
 * invariants all hold at once — same role the Phase-24 watchdog played
 * for the original i18n scaffolding.
 *
 *  - 'ar' is registered as an available_locale.
 *  - lang/ar/*.php tree is complete (mirrors lang/en/*.php).
 *  - lang/ar.json exists.
 *  - useRtlAware composable + barrel export exist.
 *  - eslint propmanager plugin + both rules are registered.
 *  - playwright.rtl.config.ts + tests/a11y/rtl/ harness exist.
 *  - LocaleHelper isRtl + dir helpers exist.
 *  - app.blade.php carries the dir attribute (Phase 43 dependency).
 */
class Phase44I18nRtlSurfaceTest extends TestCase
{
    public function test_arabic_locale_is_registered(): void
    {
        $locales = (array) config('app.available_locales');
        $this->assertArrayHasKey('ar', $locales, 'CI-1: ar must be in config(app.available_locales).');
    }

    public function test_lang_ar_tree_mirrors_english(): void
    {
        $enFiles = glob(lang_path('en/*.php'));
        $arFiles = glob(lang_path('ar/*.php'));

        $this->assertCount(
            count($enFiles),
            $arFiles,
            'CI-1: lang/ar/*.php count must equal lang/en/*.php count.',
        );

        foreach ($enFiles as $enPath) {
            $name = basename($enPath);
            $arPath = lang_path("ar/{$name}");
            $this->assertFileExists($arPath, "CI-1: lang/ar/{$name} must exist.");
        }

        $this->assertFileExists(lang_path('ar.json'), 'CI-1: lang/ar.json frontend bundle must exist.');
    }

    public function test_use_rtl_aware_composable_is_wired(): void
    {
        $this->assertFileExists(
            resource_path('js/composables/useRtlAware.ts'),
            'CI-1: useRtlAware composable must exist.',
        );

        $barrel = file_get_contents(resource_path('js/composables/index.ts'));
        $this->assertStringContainsString('useRtlAware', $barrel, 'CI-1: useRtlAware must be barrel-exported.');
    }

    public function test_eslint_propmanager_plugin_is_registered(): void
    {
        $config = file_get_contents(base_path('eslint.config.js'));

        $this->assertStringContainsString("'propmanager': propManagerPlugin", $config);
        $this->assertStringContainsString("'no-hardcoded-english-strings'", $config);
        $this->assertStringContainsString("'no-ltr-class'", $config);
    }

    public function test_playwright_rtl_harness_is_present(): void
    {
        $this->assertFileExists(
            base_path('playwright.rtl.config.ts'),
            'CI-1: playwright.rtl.config.ts must exist.',
        );
        $this->assertFileExists(
            base_path('tests/a11y/rtl/rtl-snapshot.spec.ts'),
            'CI-1: tests/a11y/rtl/rtl-snapshot.spec.ts must exist.',
        );

        $pkg = json_decode(file_get_contents(base_path('package.json')), true);
        $this->assertArrayHasKey('test:rtl', $pkg['scripts'] ?? [], 'CI-1: npm run test:rtl must be exposed.');
    }

    public function test_locale_helper_provides_rtl_metadata(): void
    {
        $helper = new \App\Support\LocaleHelper;

        $this->assertTrue($helper->isRtl('ar'), 'CI-1: LocaleHelper::isRtl(ar) must return true.');
        $this->assertSame('rtl', $helper->dir('ar'), 'CI-1: LocaleHelper::dir(ar) must return rtl.');
        $this->assertFalse($helper->isRtl('en'), 'CI-1: LocaleHelper::isRtl(en) must return false.');
        $this->assertSame('ltr', $helper->dir('en'), 'CI-1: LocaleHelper::dir(en) must return ltr.');
    }

    public function test_app_blade_carries_dir_attribute(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString(
            'dir=',
            $blade,
            'CI-1: resources/views/app.blade.php must carry a dir attribute (Phase 43 RTL-PREP).',
        );
    }
}
