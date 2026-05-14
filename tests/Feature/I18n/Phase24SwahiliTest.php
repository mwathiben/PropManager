<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Phase-24 I18N-SWAHILI-1 watchdog — the server-side Swahili
 * translations exist, mirror the English source key-for-key, and
 * actually resolve through Laravel's translator under the sw locale.
 *
 * (The exhaustive en<->sw key-parity + placeholder-token gate is
 * I18N-CI-1's Phase24CiTest — this class proves the sw files exist
 * and are wired.)
 */
class Phase24SwahiliTest extends TestCase
{
    public function test_swahili_php_lang_files_exist(): void
    {
        foreach (['messages', 'validation', 'emails', 'pdfs'] as $file) {
            $this->assertFileExists(
                lang_path("sw/{$file}.php"),
                "I18N-SWAHILI-1: lang/sw/{$file}.php must exist.",
            );
        }
    }

    public function test_swahili_files_mirror_english_key_set(): void
    {
        foreach (['messages', 'validation', 'emails', 'pdfs'] as $file) {
            $en = $this->flatten(require lang_path("en/{$file}.php"));
            $sw = $this->flatten(require lang_path("sw/{$file}.php"));

            $this->assertSame(
                array_keys($en),
                array_keys($sw),
                "I18N-SWAHILI-1: lang/sw/{$file}.php must mirror lang/en/{$file}.php key-for-key.",
            );
        }
    }

    public function test_translator_resolves_swahili(): void
    {
        App::setLocale('sw');

        // A representative key from each file resolves to Swahili,
        // not the English fallback.
        $this->assertSame('Ankara imefutwa.', __('messages.invoice.deleted'));
        $this->assertSame('Malipo Yamepokelewa', __('emails.payment.title'));
        $this->assertSame('ANKARA', __('pdfs.invoice.title'));
        $this->assertSame('Kiasi kinahitajika.', __('validation.custom.amount.required'));

        // Placeholder interpolation still works under sw.
        $this->assertSame('Zimetengenezwa ankara 5.', __('messages.invoice.generated', ['count' => 5]));
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $compound = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $result += $this->flatten($value, $compound);
            } else {
                $result[$compound] = $value;
            }
        }

        return $result;
    }
}
