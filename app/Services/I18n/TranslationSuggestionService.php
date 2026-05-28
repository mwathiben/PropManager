<?php

declare(strict_types=1);

namespace App\Services\I18n;

use App\Services\I18n\Contracts\TranslationDriverInterface;
use App\Services\I18n\Drivers\StubTranslationDriver;

/**
 * Phase-43 LANG-AUDIT-3: pluggable translation-suggestion source.
 *
 * Phase-52 DRIVER-INTERFACE-2 refactored the service to hold a
 * TranslationDriverInterface reference instead of switching on a
 * driver string. The match-on-driver logic moved into
 * TranslationDriverFactory::make.
 *
 * Used by App\Console\Commands\LangSuggest to seed missing-key
 * lang/<locale>/<namespace>.php fills.
 */
final class TranslationSuggestionService
{
    public function __construct(
        private readonly TranslationDriverInterface $driver = new StubTranslationDriver,
    ) {}

    public static function fromConfig(?TranslationDriverFactory $factory = null): self
    {
        $factory ??= new TranslationDriverFactory(new TranslationCostTracker);
        $key = (string) config('i18n.suggestion_driver', 'stub');

        return new self($factory->make($key));
    }

    public function suggest(string $sourceText, string $sourceLocale, string $targetLocale): string
    {
        return $this->driver->translate($sourceText, $sourceLocale, $targetLocale);
    }

    public function driverName(): string
    {
        return $this->driver->name();
    }
}
