<?php

declare(strict_types=1);

namespace App\Services\I18n\Drivers;

use App\Services\I18n\Contracts\TranslationDriverInterface;

/**
 * Phase-43 LANG-AUDIT-3 stub: returns the source string wrapped in a
 * locale marker. Safe for any environment (no credentials, no network)
 * and the existing CI gate around hardcoded-English baseline relies on
 * this exact prefix shape.
 *
 * Phase-52 DRIVER-INTERFACE-1 extracted this from
 * TranslationSuggestionService — service-level swappability + per-row
 * fallback on driver failure both route through here.
 */
final class StubTranslationDriver implements TranslationDriverInterface
{
    public const MARKER_PREFIX = '[TODO:';

    public function translate(string $sourceText, string $sourceLocale, string $targetLocale): string
    {
        return sprintf('%s%s] %s', self::MARKER_PREFIX, $targetLocale, $sourceText);
    }

    public function costEstimateUsd(int $characterCount): float
    {
        return 0.0;
    }

    public function name(): string
    {
        return 'stub';
    }
}
