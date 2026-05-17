<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-43 NUMERIC-FORMATTING-1/2/3: useCurrency Intl.NumberFormat
 * migration + useFormatters parity + usePhoneFormat scaffold.
 *
 * The composables themselves are TypeScript and exercised by
 * Vue-test-utils; here we assert source-level invariants that
 * downstream cycles can trust.
 */
class Phase43NumericFormattingTest extends TestCase
{
    public function test_use_currency_routes_through_intl_number_format(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/composables/useCurrency.ts'));
        $this->assertStringContainsString('Intl.NumberFormat', $source);
        $this->assertStringContainsString("currencyDisplay: 'narrowSymbol'", $source);
    }

    public function test_use_currency_exports_format_and_format_minor_helpers(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/composables/useCurrency.ts'));
        $this->assertStringContainsString('format:', $source);
        $this->assertStringContainsString('formatMinor:', $source);
    }

    public function test_use_currency_intl_locale_map_aligns_with_use_formatters(): void
    {
        $useCurrency = (string) file_get_contents(base_path('resources/js/composables/useCurrency.ts'));
        $useFormatters = (string) file_get_contents(base_path('resources/js/composables/useFormatters.ts'));
        // Both map en/sw to en-KE/sw-KE — keep this in lock-step.
        $this->assertStringContainsString("en: 'en-KE'", $useCurrency);
        $this->assertStringContainsString("sw: 'sw-KE'", $useCurrency);
        $this->assertStringContainsString('en-KE', $useFormatters);
        $this->assertStringContainsString('sw-KE', $useFormatters);
    }

    public function test_use_currency_preserves_symbol_fallback_for_intl_gaps(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/composables/useCurrency.ts'));
        $this->assertStringContainsString('SYMBOL_FALLBACK', $source);
        // KES narrow-symbol output varies across Node builds; fallback
        // pins 'KSh' so the user-visible display never drops to the
        // bare ISO 'KES'.
        $this->assertStringContainsString("KES: 'KSh'", $source);
    }

    public function test_use_phone_format_composable_exists_and_exports_formatter(): void
    {
        $path = base_path('resources/js/composables/usePhoneFormat.ts');
        $this->assertFileExists($path);
        $source = (string) file_get_contents($path);
        $this->assertStringContainsString('export function formatPhone', $source);
        $this->assertStringContainsString('export function usePhoneFormat', $source);
    }

    public function test_use_phone_format_handles_kenya_e164_national_and_compact(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/composables/usePhoneFormat.ts'));
        // Cover the three documented format outputs by name.
        $this->assertStringContainsString("'international'", $source);
        $this->assertStringContainsString("'national'", $source);
        $this->assertStringContainsString("'compact'", $source);
    }

    public function test_carbon_translated_format_used_for_user_facing_dates(): void
    {
        // Phase-24 I18N-FORMAT shipped translatedFormat across the
        // server-side date pipeline. The Phase 43 watchdog asserts
        // the convention is documented in the runbook so future
        // contributors know to use it instead of plain ->format().
        $runbook = (string) file_get_contents(base_path('docs/runbooks/i18n.md'));
        $this->assertStringContainsString('translatedFormat', $runbook);
    }
}
