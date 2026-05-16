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

    /**
     * Phase-37 PWA-RETENTION-STATS-3: two-proportion z-test for a
     * 2-variant boolean conversion metric.
     *
     * Caller supplies $isSuccessForUser($userId): bool — the
     * experimentation team decides what "success" means (e.g. paid
     * within 14 days, completed onboarding step 3). We iterate
     * every ExperimentExposure for the experiment, bucket users by
     * variant, and compute conversions.
     *
     * Returns:
     *   - variant_a / variant_b — the two variant keys
     *   - n_a / n_b — sample sizes
     *   - conversions_a / conversions_b — success counts
     *   - p_a / p_b — per-variant conversion rates
     *   - p_pooled — combined rate
     *   - z_score — two-proportion z statistic
     *   - p_value — two-tailed p-value
     *   - is_significant — true when p_value < 0.05
     *
     * Throws InvalidArgumentException when the experiment doesn't
     * have exactly 2 variants — multi-variant + Bayesian land in
     * Phase 38+ candidates.
     */
    public function computeSignificance(string $experimentKey, \Closure $isSuccessForUser): array
    {
        $experiment = $this->loadExperiment($experimentKey);
        if ($experiment === null) {
            throw new \InvalidArgumentException("Unknown experiment '{$experimentKey}'.");
        }

        $variants = $experiment->variants ?? [];
        if (count($variants) !== 2) {
            throw new \InvalidArgumentException(
                'computeSignificance requires exactly 2 variants; got '.count($variants).'.',
            );
        }

        $variantAKey = (string) ($variants[0]['key'] ?? Experiment::CONTROL_VARIANT);
        $variantBKey = (string) ($variants[1]['key'] ?? Experiment::CONTROL_VARIANT);

        $bucket = [$variantAKey => ['n' => 0, 'c' => 0], $variantBKey => ['n' => 0, 'c' => 0]];

        ExperimentExposure::query()
            ->where('experiment_key', $experimentKey)
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$bucket, $isSuccessForUser, $variantAKey, $variantBKey) {
                foreach ($rows as $row) {
                    $variant = (string) $row->variant_key;
                    if ($variant !== $variantAKey && $variant !== $variantBKey) {
                        continue;
                    }
                    $bucket[$variant]['n']++;
                    if ($isSuccessForUser((int) $row->user_id)) {
                        $bucket[$variant]['c']++;
                    }
                }
            });

        $nA = $bucket[$variantAKey]['n'];
        $nB = $bucket[$variantBKey]['n'];
        $cA = $bucket[$variantAKey]['c'];
        $cB = $bucket[$variantBKey]['c'];

        $pA = $nA > 0 ? $cA / $nA : 0.0;
        $pB = $nB > 0 ? $cB / $nB : 0.0;
        $pPooled = ($nA + $nB) > 0 ? ($cA + $cB) / ($nA + $nB) : 0.0;

        $z = 0.0;
        if ($nA > 0 && $nB > 0 && $pPooled > 0.0 && $pPooled < 1.0) {
            $se = sqrt($pPooled * (1 - $pPooled) * ((1 / $nA) + (1 / $nB)));
            $z = $se > 0 ? ($pA - $pB) / $se : 0.0;
        }

        $pValue = 2 * (1 - $this->standardNormalCdf(abs($z)));
        $isSignificant = $pValue < 0.05;

        return [
            'variant_a' => $variantAKey,
            'variant_b' => $variantBKey,
            'n_a' => $nA,
            'n_b' => $nB,
            'conversions_a' => $cA,
            'conversions_b' => $cB,
            'p_a' => round($pA, 6),
            'p_b' => round($pB, 6),
            'p_pooled' => round($pPooled, 6),
            'z_score' => round($z, 6),
            'p_value' => round($pValue, 6),
            'is_significant' => $isSignificant,
        ];
    }

    /**
     * Abramowitz & Stegun 26.2.17 approximation of the standard
     * normal CDF Φ(x). Accurate to ~7.5e-8 absolute error — more
     * than enough for α=0.05 significance calls.
     */
    private function standardNormalCdf(float $x): float
    {
        if ($x === 0.0) {
            return 0.5;
        }
        $b1 = 0.319381530;
        $b2 = -0.356563782;
        $b3 = 1.781477937;
        $b4 = -1.821255978;
        $b5 = 1.330274429;
        $p = 0.2316419;
        $absX = abs($x);
        $t = 1.0 / (1.0 + $p * $absX);
        $pdf = (1.0 / sqrt(2 * M_PI)) * exp(-($absX * $absX) / 2);
        $cdf = 1.0 - $pdf * ($b1 * $t + $b2 * $t ** 2 + $b3 * $t ** 3 + $b4 * $t ** 4 + $b5 * $t ** 5);

        return $x >= 0 ? $cdf : 1.0 - $cdf;
    }
}
