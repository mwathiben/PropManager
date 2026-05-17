<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-43 [I18N-DEPTH] cycle watchdog — consolidates the
 * invariants of this audit cycle so future i18n work has one
 * place to notice regressions.
 *
 * Mirrors Phase42PaymentsSurfaceTest in shape:
 *   - EXPECTED_COMMANDS  — 4 new artisan commands.
 *   - EXPECTED_SCHEDULES — lang:audit at 04:15 Africa/Nairobi.
 *   - EXPECTED_SERVICES  — 3 new helpers / services.
 *   - EXPECTED_RUNBOOK_SECTIONS — i18n.md Phase 43 additions.
 *   - EXPECTED_RUNTIME_FILES — codemod script + composables.
 */
class Phase43I18nSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_COMMANDS = [
        'lang:coverage',
        'lang:audit',
        'lang:check',
        'lang:suggest',
    ];

    private const EXPECTED_SERVICES = [
        \App\Support\I18nKeyExtractor::class,
        \App\Support\HardcodedEnglishScanner::class,
        \App\Support\LangBundleLoader::class,
        \App\Support\LocaleHelper::class,
        \App\Services\I18n\TranslationSuggestionService::class,
    ];

    private const EXPECTED_RUNTIME_FILES = [
        'scripts/migrate-to-logical-properties.mjs',
        'resources/js/composables/usePhoneFormat.ts',
        'resources/js/composables/useCurrency.ts',
        'resources/js/composables/useFormatters.ts',
        'config/i18n.php',
    ];

    public function test_all_expected_phase_43_commands_registered(): void
    {
        $registered = Artisan::all();
        foreach (self::EXPECTED_COMMANDS as $name) {
            $this->assertArrayHasKey($name, $registered, "Phase-43 command `{$name}` not registered.");
        }
    }

    public function test_lang_audit_scheduled_daily_at_04_15_africa_nairobi(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'lang:audit'));
        $this->assertNotNull($entry);
        $this->assertSame('15 4 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_all_expected_phase_43_services_exist(): void
    {
        foreach (self::EXPECTED_SERVICES as $cls) {
            $this->assertTrue(class_exists($cls), "Phase-43 class `{$cls}` not present.");
        }
    }

    public function test_all_expected_phase_43_runtime_files_exist(): void
    {
        foreach (self::EXPECTED_RUNTIME_FILES as $path) {
            $this->assertFileExists(base_path($path), "Phase-43 file `{$path}` missing.");
        }
    }

    public function test_pinned_namespaces_config_present(): void
    {
        $pinned = (array) config('i18n.pinned_namespaces');
        $this->assertNotEmpty($pinned);
        foreach (['auth', 'common', 'validation', 'payments'] as $expected) {
            $this->assertContains($expected, $pinned);
        }
    }

    public function test_rtl_locales_config_present(): void
    {
        $rtl = (array) config('i18n.rtl_locales');
        $this->assertNotEmpty($rtl);
        foreach (['ar', 'he', 'fa', 'ur'] as $expected) {
            $this->assertContains($expected, $rtl);
        }
    }

    public function test_i18n_runbook_has_phase_43_sections(): void
    {
        $runbook = (string) file_get_contents(base_path('docs/runbooks/i18n.md'));
        $this->assertStringContainsString('Phase 43 [I18N-DEPTH] additions', $runbook);
        $this->assertStringContainsString('lang:coverage', $runbook);
        $this->assertStringContainsString('lang:audit', $runbook);
        $this->assertStringContainsString('lang:suggest', $runbook);
        $this->assertStringContainsString('Hardcoded English baseline', $runbook);
        $this->assertStringContainsString('RTL support', $runbook);
    }

    public function test_alert_thresholds_carries_i18n_missing_keys_rows(): void
    {
        $alerts = (string) file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('i18n missing keys — pinned namespace', $alerts);
        $this->assertStringContainsString('i18n missing keys — loose namespace', $alerts);
        $this->assertStringContainsString('i18n_missing_keys_count', $alerts);
    }

    public function test_html_template_carries_dir_and_hreflang(): void
    {
        $blade = (string) file_get_contents(base_path('resources/views/app.blade.php'));
        $this->assertStringContainsString('dir=', $blade);
        $this->assertStringContainsString('hreflang=', $blade);
    }
}
