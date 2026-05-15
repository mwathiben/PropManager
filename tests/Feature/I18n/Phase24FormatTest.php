<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Enums\Currency;
use Tests\TestCase;

/**
 * Phase-24 I18N-FORMAT-1 / I18N-FORMAT-2 watchdogs.
 *
 * Dates, relative phrases, currency and number formatting must track
 * the user's chosen locale — not a hardcoded 'en-KE' / 'en-GB' pair.
 * useFormatters reads the shared `locale` prop and maps it to a BCP-47
 * tag for Intl.*; relative-date phrasing comes from vue-i18n keys; the
 * Currency enum gains a sw-KE branch so server-formatted numbers also
 * track the active locale.
 */
class Phase24FormatTest extends TestCase
{
    public function test_useformatters_drives_intl_locale_from_shared_prop(): void
    {
        $contents = file_get_contents(resource_path('js/composables/useFormatters.ts'));

        // The composable must read the Inertia-shared locale, not just currency.
        $this->assertStringContainsString(
            'page.props.locale',
            $contents,
            'I18N-FORMAT-1: useFormatters must read the shared `locale` prop.',
        );

        // Both an 'en' and 'sw' Intl mapping must exist (en-KE/en-GB
        // for English; sw-KE for Swahili).
        $this->assertStringContainsString(
            "'sw-KE'",
            $contents,
            'I18N-FORMAT-1: useFormatters must map sw → a Swahili BCP-47 tag.',
        );
        $this->assertStringContainsString(
            "'en-KE'",
            $contents,
            'I18N-FORMAT-1: useFormatters must keep the en-KE mapping for English.',
        );
    }

    public function test_useformatters_relative_phrases_come_from_i18n_keys(): void
    {
        $contents = file_get_contents(resource_path('js/composables/useFormatters.ts'));

        // The vue-i18n engine must be the source of truth for relative
        // phrases — not English literals baked into the composable.
        $this->assertStringContainsString(
            "from 'vue-i18n'",
            $contents,
            'I18N-FORMAT-1: useFormatters must import vue-i18n.',
        );
        $this->assertStringContainsString(
            'format.relative.yesterday',
            $contents,
            'I18N-FORMAT-1: relative phrasing must use the format.relative.* keys.',
        );
        $this->assertStringContainsString(
            'format.relative.just_now',
            $contents,
            'I18N-FORMAT-1: relative phrasing must use the format.relative.* keys.',
        );
    }

    public function test_relative_format_keys_present_in_every_locale_bundle(): void
    {
        $required = [
            'format.relative.yesterday',
            'format.relative.today',
            'format.relative.tomorrow',
            'format.relative.just_now',
            'format.relative.days_ago',
            'format.relative.in_days',
            'format.relative.minutes_ago',
            'format.relative.hours_ago',
        ];

        foreach (array_keys(config('app.available_locales')) as $locale) {
            $path = lang_path("{$locale}.json");
            $bundle = json_decode(file_get_contents($path), true) ?: [];

            foreach ($required as $key) {
                $this->assertNotSame(
                    null,
                    data_get($bundle, $key),
                    "I18N-FORMAT-1: lang/{$locale}.json must define '{$key}'.",
                );
            }
        }
    }

    public function test_currency_enum_has_swahili_locale_branch(): void
    {
        // KES drives the Kenyan landlord/tenant default — when the
        // user switches to Swahili the server-formatted figures should
        // also use the sw-KE tag. The Currency enum is the canonical
        // server-side locale source for currency formatting.
        $reflection = new \ReflectionClass(Currency::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringContainsString(
            "'sw-KE'",
            $source,
            'I18N-FORMAT-2: Currency::locale() must expose a sw-KE branch for Swahili formatting.',
        );
    }
}
