<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phase-56 AB-AUTO-PROMOTE-3: fires when ExperimentsAutoPromote
 * transitions a running experiment to concluded after the dual
 * chi-square + Bayesian significance gate passes.
 */
class ExperimentConcluded
{
    use Dispatchable;

    public function __construct(
        public readonly string $experimentKey,
        public readonly string $winningVariantKey,
        public readonly float $chiPValue,
        public readonly float $bayesPosterior,
    ) {}
}
