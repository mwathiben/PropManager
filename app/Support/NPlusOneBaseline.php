<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phase-22 PERF-NPLUS1-1: the lazy-load allow-list (baseline).
 *
 * Pre-Phase-22, Model::preventLazyLoading was active in non-production
 * but the violation handler only LOGGED — so a lazy-load in a tested
 * code path was invisible to CI. PERF-NPLUS1-1 promotes the handler to
 * THROW in the testing environment, turning every N+1 into a failing
 * test.
 *
 * Flipping that on a mature suite surfaces pre-existing violations all
 * at once. Rather than block the gate on fixing them all in one commit,
 * the known offenders are listed here as `Model::relation` pairs: a
 * pair on the list is logged (not thrown), a pair NOT on the list
 * throws immediately. New N+1s therefore fail CI the moment they land.
 *
 * SHRINK-ONLY CONTRACT: this list may only ever get shorter. PERF-NPLUS1-2
 * works it down — each fix removes a pair and ratchets the
 * Phase22NPlusOneTest threshold. Nothing may be added here without a
 * code-review justification; the watchdog pins the count.
 */
final class NPlusOneBaseline
{
    /**
     * Known lazy-load offenders, as `FullyQualified\Model::relation`.
     *
     * @var list<string>
     */
    public const ALLOWED = [
        // Populated from the first full-suite run with the gate active.
        // PERF-NPLUS1-2 drives this to empty.
    ];

    public static function isAllowed(string $modelClass, string $relation): bool
    {
        return in_array($modelClass.'::'.$relation, self::ALLOWED, true);
    }
}
