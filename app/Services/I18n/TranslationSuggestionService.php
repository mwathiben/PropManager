<?php

declare(strict_types=1);

namespace App\Services\I18n;

/**
 * Phase-43 LANG-AUDIT-3: pluggable translation-suggestion
 * source. The default Stub driver returns
 * `[TODO:locale] <english>` placeholders — safe for any
 * environment, no creds required. Google + DeepL drivers can
 * graft on later behind config('i18n.suggestion_driver') without
 * touching caller code.
 *
 * Used by App\Console\Commands\LangSuggest to seed missing-key
 * lang/<locale>/<namespace>.php fills.
 */
final class TranslationSuggestionService
{
    public function __construct(
        private readonly string $driver = 'stub',
        private readonly ?string $googleApiKey = null,
        private readonly ?string $deeplApiKey = null,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            driver: (string) config('i18n.suggestion_driver', 'stub'),
            googleApiKey: config('i18n.google_api_key'),
            deeplApiKey: config('i18n.deepl_api_key'),
        );
    }

    public function suggest(string $sourceText, string $sourceLocale, string $targetLocale): string
    {
        return match ($this->driver) {
            'google' => $this->google($sourceText, $sourceLocale, $targetLocale),
            'deepl' => $this->deepl($sourceText, $sourceLocale, $targetLocale),
            default => $this->stub($sourceText, $targetLocale),
        };
    }

    private function stub(string $sourceText, string $targetLocale): string
    {
        return sprintf('[TODO:%s] %s', $targetLocale, $sourceText);
    }

    private function google(string $sourceText, string $sourceLocale, string $targetLocale): string
    {
        if ($this->googleApiKey === null || $this->googleApiKey === '') {
            return $this->stub($sourceText, $targetLocale);
        }

        // Real implementation deferred. Scope of LANG-AUDIT-3 in
        // Phase 43 is the plug-in surface; Phase 44+ wires the
        // actual API once the workflow proves out via Stub.
        return $this->stub($sourceText, $targetLocale);
    }

    private function deepl(string $sourceText, string $sourceLocale, string $targetLocale): string
    {
        if ($this->deeplApiKey === null || $this->deeplApiKey === '') {
            return $this->stub($sourceText, $targetLocale);
        }

        return $this->stub($sourceText, $targetLocale);
    }
}
