<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Support\LocaleHelper;
use Tests\TestCase;

/**
 * Phase-43 RTL-PREP-1/2/3: dir attribute + LocaleHelper +
 * codemod scaffold. Phase 43 ships the plumbing only — Phase 44
 * [I18N-RTL] is the mass component-class migration.
 */
class Phase43RtlPrepTest extends TestCase
{
    public function test_locale_helper_class_exists(): void
    {
        $this->assertTrue(class_exists(LocaleHelper::class));
    }

    public function test_rtl_locales_config_present_and_includes_arabic_hebrew_farsi_urdu(): void
    {
        $rtl = (array) config('i18n.rtl_locales');
        $this->assertNotEmpty($rtl);
        foreach (['ar', 'he', 'fa', 'ur'] as $expected) {
            $this->assertContains($expected, $rtl);
        }
    }

    public function test_codemod_script_exists_and_is_a_node_module(): void
    {
        $path = base_path('scripts/migrate-to-logical-properties.mjs');
        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('export function transform', $contents);
        $this->assertStringContainsString("'ms-'", $contents);
        $this->assertStringContainsString("'me-'", $contents);
        $this->assertStringContainsString("'ps-'", $contents);
        $this->assertStringContainsString("'pe-'", $contents);
    }

    public function test_codemod_script_documents_dry_run_and_apply_modes(): void
    {
        $contents = (string) file_get_contents(base_path('scripts/migrate-to-logical-properties.mjs'));
        $this->assertStringContainsString('--dry-run', $contents);
        $this->assertStringContainsString('--apply', $contents);
    }

    public function test_app_blade_carries_dir_attribute(): void
    {
        $blade = (string) file_get_contents(base_path('resources/views/app.blade.php'));
        $this->assertStringContainsString('dir=', $blade);
        $this->assertStringContainsString('$localeHelper->dir()', $blade);
    }

    public function test_app_blade_carries_hreflang_link_tags(): void
    {
        $blade = (string) file_get_contents(base_path('resources/views/app.blade.php'));
        $this->assertStringContainsString('hreflang=', $blade);
        $this->assertStringContainsString('rel="alternate"', $blade);
    }
}
