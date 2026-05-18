<?php

declare(strict_types=1);

namespace App\Listeners\Growth;

use App\Events\ExperimentConcluded;
use App\Services\Platform\ProductEventTracker;

/**
 * Phase-56 AB-AUTO-PROMOTE-3: write a product_events 'experiment.concluded'
 * row when an experiment auto-promotes so the operator timeline has an
 * auditable record of every flip.
 *
 * Synchronous — lightweight enough that queueing would be overhead.
 */
class LogExperimentConclusion
{
    public function __construct(private readonly ProductEventTracker $tracker) {}

    public function handle(ExperimentConcluded $event): void
    {
        $this->tracker->track('experiment.concluded', [
            'experiment_key' => $event->experimentKey,
            'winning_variant_key' => $event->winningVariantKey,
            'chi_p' => $event->chiPValue,
            'bayes_posterior' => $event->bayesPosterior,
        ]);
    }
}
