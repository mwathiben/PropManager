<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Support\I18nKeyExtractor;
use Tests\TestCase;

/**
 * Phase-43 LANG-COVERAGE-1: every $t() call in resources/js
 * resolves against the merged en lang bundle. Catches the class
 * of bug Phase-42 hot-fix `16cbafd` patched manually.
 */
class Phase43LangCoverageTest extends TestCase
{
    public function test_lang_coverage_command_exits_zero_for_default_dir(): void
    {
        $this->artisan('lang:coverage')
            ->assertExitCode(0);
    }

    public function test_lang_coverage_command_reports_missing_keys_for_synthetic_input(): void
    {
        $tmp = sys_get_temp_dir().'/phase43-coverage-'.uniqid();
        mkdir($tmp);
        file_put_contents($tmp.'/synthetic.vue', '<template>{{ $t(\'nonexistent.synthetic_key\') }}</template>');

        $this->artisan('lang:coverage', ['--dir' => $tmp])
            ->assertExitCode(1);

        unlink($tmp.'/synthetic.vue');
        rmdir($tmp);
    }

    public function test_i18n_key_extractor_finds_vue_template_calls(): void
    {
        $extractor = new I18nKeyExtractor();
        $keys = $extractor->extractFromString('<template>{{ $t(\'a.b.c\') }}{{ $t("d.e") }}</template>');
        $this->assertContains('a.b.c', $keys);
        $this->assertContains('d.e', $keys);
    }

    public function test_i18n_key_extractor_finds_composition_api_t_calls(): void
    {
        $extractor = new I18nKeyExtractor();
        $keys = $extractor->extractFromString('const heading = t(\'cart.heading\');');
        $this->assertContains('cart.heading', $keys);
    }

    public function test_i18n_key_extractor_ignores_dynamic_keys(): void
    {
        $extractor = new I18nKeyExtractor();
        $keys = $extractor->extractFromString('$t(`prefix.${suffix}`)');
        $this->assertNotContains('prefix.${suffix}', $keys);
        $this->assertSame([], $keys);
    }

    public function test_i18n_key_extractor_deduplicates(): void
    {
        $extractor = new I18nKeyExtractor();
        $keys = $extractor->extractFromString('$t("a.b") + $t("a.b") + $t("a.b")');
        $this->assertSame(['a.b'], $keys);
    }
}
