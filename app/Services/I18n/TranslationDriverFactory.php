<?php

declare(strict_types=1);

namespace App\Services\I18n;

use App\Services\I18n\Contracts\TranslationDriverInterface;
use App\Services\I18n\Drivers\DeepLTranslationDriver;
use App\Services\I18n\Drivers\GoogleTranslationDriver;
use App\Services\I18n\Drivers\StubTranslationDriver;
use InvalidArgumentException;

/**
 * Phase-52 DRIVER-INTERFACE-3: resolves a driver instance from a key
 * string ('stub' / 'google' / 'deepl'). Wraps non-stub drivers with
 * the CostAwareDriver decorator so the budget guard kicks in.
 *
 * The factory is the only place driver instantiation logic lives —
 * lang:suggest, TranslationSuggestionService::fromConfig, and tests
 * all go through here.
 */
final class TranslationDriverFactory
{
    public function __construct(
        protected ?TranslationCostTracker $costTracker = null,
    ) {}

    public function make(string $driver): TranslationDriverInterface
    {
        return match ($driver) {
            'stub' => new StubTranslationDriver,
            'google' => $this->wrap(new GoogleTranslationDriver(
                apiKey: (string) (config('i18n.google_api_key') ?? ''),
            )),
            'deepl' => $this->wrap(new DeepLTranslationDriver(
                apiKey: (string) (config('i18n.deepl_api_key') ?? ''),
                formality: $this->stringOrNull(config('i18n.deepl_formality')),
                glossaryId: $this->stringOrNull(config('i18n.deepl_glossary_id')),
            )),
            default => throw new InvalidArgumentException("Unknown translation driver: {$driver}"),
        };
    }

    /**
     * Wrap any non-stub driver with the CostAwareDriver decorator so
     * canSpend() runs before every call and record() runs after.
     */
    private function wrap(TranslationDriverInterface $driver): TranslationDriverInterface
    {
        if ($this->costTracker === null) {
            return $driver;
        }

        return new CostAwareDriver($driver, $this->costTracker, new StubTranslationDriver);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
