<?php

declare(strict_types=1);

namespace App\Services\I18n;

use App\Services\I18n\Contracts\TranslationDriverInterface;

/**
 * Phase-52 COST-GUARD-1: decorates a translation driver with the
 * pre-flight budget check + post-call cost record.
 *
 * If the prospective spend would exceed the daily budget, the
 * decorator routes through a fallback driver (typically the stub)
 * so the caller still gets a usable string. The cost tracker is the
 * authority — every non-stub translate() call MUST go through here.
 */
final class CostAwareDriver implements TranslationDriverInterface
{
    public function __construct(
        protected TranslationDriverInterface $inner,
        protected TranslationCostTracker $tracker,
        protected TranslationDriverInterface $fallback,
    ) {}

    public function translate(string $sourceText, string $sourceLocale, string $targetLocale): string
    {
        $charCount = mb_strlen($sourceText);
        $estimate = $this->inner->costEstimateUsd($charCount);

        if (! $this->tracker->canSpend($estimate)) {
            return $this->fallback->translate($sourceText, $sourceLocale, $targetLocale);
        }

        $result = $this->inner->translate($sourceText, $sourceLocale, $targetLocale);

        // Only record cost when the driver returned a non-stub result.
        // Drivers that fall back internally (e.g. on 4xx) signal via the
        // [TODO: marker so we don't double-charge for failed calls.
        if (! str_starts_with($result, '[TODO:')) {
            $this->tracker->record($this->inner->name(), $targetLocale, $charCount, $estimate);
        }

        return $result;
    }

    public function costEstimateUsd(int $characterCount): float
    {
        return $this->inner->costEstimateUsd($characterCount);
    }

    public function name(): string
    {
        return $this->inner->name();
    }
}
