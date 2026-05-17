<?php

declare(strict_types=1);

namespace App\Services\I18n\Contracts;

/**
 * Phase-52 DRIVER-INTERFACE-1: contract for the swappable translation
 * source backing TranslationSuggestionService.
 *
 * Implementations MUST be deterministic with respect to a given
 * (text, source, target) tuple — the cost tracker assumes one call =
 * one charge. Caching/retry are caller concerns, NOT driver concerns.
 *
 * costEstimateUsd returns the per-call cost so the cost guard can run
 * a pre-flight `canSpend()` check before invoking the driver. The
 * estimate is non-binding — drivers that don't know their cost (Stub)
 * return 0.0.
 */
interface TranslationDriverInterface
{
    public function translate(string $sourceText, string $sourceLocale, string $targetLocale): string;

    public function costEstimateUsd(int $characterCount): float;

    public function name(): string;
}
