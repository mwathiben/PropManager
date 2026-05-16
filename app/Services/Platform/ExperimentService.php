<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Experiment;
use App\Models\ExperimentExposure;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-35 PLATFORM-EXP-2: deterministic-hash variant assignment.
 *
 *   - variantFor(user, key) returns the variant for that user, or
 *     null if no active experiment under that key.
 *   - Sticky: same user always sees same variant within an
 *     experiment, even if weights change mid-flight (the existing
 *     ExperimentExposure row short-circuits the hash).
 *   - Fail-open: any error returns null (control). Analytics +
 *     experiment infrastructure must NEVER block a user request.
 *   - Concluded experiments with a winning_variant_key return the
 *     winner unconditionally (post-experiment rollout).
 */
class ExperimentService
{
    public function variantFor(User $user, string $experimentKey): ?string
    {
        try {
            $experiment = $this->loadExperiment($experimentKey);
            if ($experiment === null) {
                return null;
            }

            if ($experiment->status === Experiment::STATUS_CONCLUDED) {
                return $experiment->winning_variant_key;
            }

            if (! $experiment->isActive()) {
                return Experiment::CONTROL_VARIANT;
            }

            $existing = ExperimentExposure::query()
                ->where('user_id', $user->id)
                ->where('experiment_key', $experimentKey)
                ->first();
            if ($existing) {
                return $existing->variant_key;
            }

            $assigned = $this->assignVariant($user, $experiment);

            ExperimentExposure::create([
                'user_id' => $user->id,
                'experiment_key' => $experimentKey,
                'variant_key' => $assigned,
                'fired_at' => now(),
            ]);

            return $assigned;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns map of experiment_key => variant_key for every active
     * experiment, for the given user. Used by the Inertia share
     * (EXP-3) so the page render reflects assigned variants.
     */
    public function activeFor(User $user): array
    {
        try {
            $running = $this->runningExperiments();
            $out = [];
            foreach ($running as $exp) {
                $variant = $this->variantFor($user, $exp->experiment_key);
                if ($variant !== null) {
                    $out[$exp->experiment_key] = $variant;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private function assignVariant(User $user, Experiment $experiment): string
    {
        $variants = $experiment->variants ?? [];
        if ($variants === []) {
            return Experiment::CONTROL_VARIANT;
        }

        $hash = crc32($user->id.':'.$experiment->experiment_key) % 100;
        $cursor = 0;
        foreach ($variants as $variant) {
            $cursor += (int) ($variant['weight'] ?? 0);
            if ($hash < $cursor) {
                return (string) ($variant['key'] ?? Experiment::CONTROL_VARIANT);
            }
        }

        return (string) ($variants[count($variants) - 1]['key'] ?? Experiment::CONTROL_VARIANT);
    }

    private function loadExperiment(string $experimentKey): ?Experiment
    {
        return Experiment::query()
            ->where('experiment_key', $experimentKey)
            ->first();
    }

    /**
     * Cached 60s — Inertia share fires on every request, we do not
     * want a DB hit per request for the experiment list.
     */
    private function runningExperiments()
    {
        return Cache::remember('experiments:running', 60, function () {
            return Experiment::query()
                ->where('status', Experiment::STATUS_RUNNING)
                ->get();
        });
    }
}
