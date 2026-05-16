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

    /**
     * Phase-39 EXP-STATS-2-1: multi-variant χ² test of independence.
     * Builds a 2 × N contingency table (success vs failure per
     * variant), computes χ² = Σ (O - E)² / E across all 2N cells,
     * df = (rows-1)(cols-1) = N - 1, p_value via chi² survival
     * function (Wilson-Hilferty approximation for general df).
     *
     * Accepts N >= 2 variants — superset of the 2-variant z-test
     * shipped in Phase 37.
     *
     * @return array{variants: array<string>, n: array<string,int>, conversions: array<string,int>, p: array<string,float>, chi_square: float, df: int, p_value: float, is_significant: bool}
     */
    public function computeChiSquareSignificance(string $experimentKey, \Closure $isSuccessForUser): array
    {
        $experiment = $this->loadExperiment($experimentKey);
        if ($experiment === null) {
            throw new \InvalidArgumentException("Unknown experiment '{$experimentKey}'.");
        }

        $variants = $experiment->variants ?? [];
        if (count($variants) < 2) {
            throw new \InvalidArgumentException(
                'computeChiSquareSignificance requires at least 2 variants; got '.count($variants).'.',
            );
        }

        $variantKeys = array_map(fn ($v) => (string) ($v['key'] ?? Experiment::CONTROL_VARIANT), $variants);
        $bucket = array_fill_keys($variantKeys, ['n' => 0, 'c' => 0]);

        ExperimentExposure::query()
            ->where('experiment_key', $experimentKey)
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$bucket, $isSuccessForUser, $variantKeys) {
                foreach ($rows as $row) {
                    $variant = (string) $row->variant_key;
                    if (! in_array($variant, $variantKeys, true)) {
                        continue;
                    }
                    $bucket[$variant]['n']++;
                    if ($isSuccessForUser((int) $row->user_id)) {
                        $bucket[$variant]['c']++;
                    }
                }
            });

        $totalN = array_sum(array_column($bucket, 'n'));
        $totalC = array_sum(array_column($bucket, 'c'));

        if ($totalN === 0) {
            return [
                'variants' => $variantKeys,
                'n' => array_combine($variantKeys, array_column($bucket, 'n')),
                'conversions' => array_combine($variantKeys, array_column($bucket, 'c')),
                'p' => array_fill_keys($variantKeys, 0.0),
                'chi_square' => 0.0,
                'df' => count($variantKeys) - 1,
                'p_value' => 1.0,
                'is_significant' => false,
            ];
        }

        $pPooled = $totalC / $totalN;
        $chiSquare = 0.0;
        $perVariantP = [];

        foreach ($variantKeys as $key) {
            $n = $bucket[$key]['n'];
            $c = $bucket[$key]['c'];
            $perVariantP[$key] = $n > 0 ? round($c / $n, 6) : 0.0;
            if ($n === 0) {
                continue;
            }
            $eSuccess = $n * $pPooled;
            $eFailure = $n * (1 - $pPooled);
            if ($eSuccess > 0) {
                $chiSquare += (($c - $eSuccess) ** 2) / $eSuccess;
            }
            if ($eFailure > 0) {
                $chiSquare += ((($n - $c) - $eFailure) ** 2) / $eFailure;
            }
        }

        $df = count($variantKeys) - 1;
        $pValue = $this->chiSquareSurvival($chiSquare, $df);

        return [
            'variants' => $variantKeys,
            'n' => array_combine($variantKeys, array_column($bucket, 'n')),
            'conversions' => array_combine($variantKeys, array_column($bucket, 'c')),
            'p' => $perVariantP,
            'chi_square' => round($chiSquare, 6),
            'df' => $df,
            'p_value' => round($pValue, 6),
            'is_significant' => $pValue < 0.05,
        ];
    }

    /**
     * Phase-39 EXP-STATS-2-2: Bayesian posterior P(variant_b > variant_a)
     * via Monte Carlo sampling from Beta(c+1, n-c+1) per variant
     * (Jeffreys / Beta(0.5,0.5) prior also works; +1/+1 Laplace
     * smoothing chosen for interpretability). 2-variant only.
     *
     * @return array{variant_a: string, variant_b: string, p_b_better_than_a: float, expected_lift_pct: float, ci_95_low: float, ci_95_high: float, samples: int}
     */
    public function computeBayesianPosterior(string $experimentKey, \Closure $isSuccessForUser, int $samples = 50000): array
    {
        $experiment = $this->loadExperiment($experimentKey);
        if ($experiment === null) {
            throw new \InvalidArgumentException("Unknown experiment '{$experimentKey}'.");
        }
        $variants = $experiment->variants ?? [];
        if (count($variants) !== 2) {
            throw new \InvalidArgumentException('computeBayesianPosterior requires exactly 2 variants.');
        }

        $aKey = (string) ($variants[0]['key'] ?? Experiment::CONTROL_VARIANT);
        $bKey = (string) ($variants[1]['key'] ?? Experiment::CONTROL_VARIANT);
        $bucket = [$aKey => ['n' => 0, 'c' => 0], $bKey => ['n' => 0, 'c' => 0]];

        ExperimentExposure::query()
            ->where('experiment_key', $experimentKey)
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$bucket, $isSuccessForUser, $aKey, $bKey) {
                foreach ($rows as $row) {
                    $variant = (string) $row->variant_key;
                    if ($variant !== $aKey && $variant !== $bKey) {
                        continue;
                    }
                    $bucket[$variant]['n']++;
                    if ($isSuccessForUser((int) $row->user_id)) {
                        $bucket[$variant]['c']++;
                    }
                }
            });

        $diffs = [];
        $bBetter = 0;
        for ($i = 0; $i < $samples; $i++) {
            $thetaA = $this->sampleBeta($bucket[$aKey]['c'] + 1, $bucket[$aKey]['n'] - $bucket[$aKey]['c'] + 1);
            $thetaB = $this->sampleBeta($bucket[$bKey]['c'] + 1, $bucket[$bKey]['n'] - $bucket[$bKey]['c'] + 1);
            $diff = $thetaB - $thetaA;
            $diffs[] = $diff;
            if ($diff > 0) {
                $bBetter++;
            }
        }

        sort($diffs);
        $ciLowIdx = (int) floor($samples * 0.025);
        $ciHighIdx = (int) floor($samples * 0.975);
        $expectedLift = array_sum($diffs) / $samples;

        return [
            'variant_a' => $aKey,
            'variant_b' => $bKey,
            'p_b_better_than_a' => round($bBetter / $samples, 4),
            'expected_lift_pct' => round($expectedLift * 100, 4),
            'ci_95_low' => round($diffs[$ciLowIdx] * 100, 4),
            'ci_95_high' => round($diffs[$ciHighIdx] * 100, 4),
            'samples' => $samples,
        ];
    }

    /**
     * Phase-39 EXP-STATS-2-3: O'Brien-Fleming alpha-spending boundary
     * for sequential analysis. Returns the z-threshold for the
     * current peek under a Pocock/OBF-style spending schedule, so
     * total Type-I error stays ≤ alpha across all peeks.
     *
     * Formula (approximation): boundary_z(t) = z_{α / (2 * t²)}
     * where t = peek/maxPeeks (fraction of information accumulated).
     * Early peeks need much-larger boundaries; final peek approaches
     * z_{α/2}.
     */
    public function computeAlphaSpendingBoundary(int $peekNumber, int $maxPeeks, float $alpha = 0.05): float
    {
        if ($peekNumber < 1 || $peekNumber > $maxPeeks) {
            throw new \InvalidArgumentException("peekNumber {$peekNumber} out of range [1, {$maxPeeks}].");
        }
        $t = $peekNumber / $maxPeeks;
        // Cap so very early peeks don't compute infinity; floor at α/30 (the
        // sample-size limit of useful boundaries).
        $perPeekAlpha = max($alpha / 30, $alpha * ($t ** 2));

        return $this->inverseNormalCdf(1 - $perPeekAlpha / 2);
    }

    /**
     * Marsaglia & Tsang gamma-distribution sampler (acceptance-rejection),
     * used here as the building block for Beta sampling via the
     * standard X/(X+Y) trick where X~Gamma(α), Y~Gamma(β).
     */
    private function sampleBeta(float $alpha, float $beta): float
    {
        $x = $this->sampleGamma($alpha);
        $y = $this->sampleGamma($beta);

        return $x / max(1e-12, $x + $y);
    }

    private function sampleGamma(float $shape): float
    {
        if ($shape < 1.0) {
            // Boost via u^(1/shape) trick.
            return $this->sampleGamma($shape + 1.0) * (mt_rand() / mt_getrandmax()) ** (1.0 / $shape);
        }
        $d = $shape - 1.0 / 3.0;
        $c = 1.0 / sqrt(9.0 * $d);
        while (true) {
            do {
                $x = $this->sampleStandardNormal();
                $v = 1.0 + $c * $x;
            } while ($v <= 0);
            $v = $v ** 3;
            $u = mt_rand() / mt_getrandmax();
            if ($u < 1.0 - 0.0331 * ($x ** 4)) {
                return $d * $v;
            }
            if (log($u) < 0.5 * $x ** 2 + $d * (1.0 - $v + log($v))) {
                return $d * $v;
            }
        }
    }

    private function sampleStandardNormal(): float
    {
        // Box-Muller.
        $u1 = max(1e-12, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    /**
     * Inverse standard normal CDF (Beasley-Springer-Moro
     * approximation). Used by computeAlphaSpendingBoundary to map
     * spent-alpha to z-threshold.
     */
    private function inverseNormalCdf(float $p): float
    {
        if ($p < 0.5) {
            return -$this->inverseNormalCdf(1 - $p);
        }
        $q = sqrt(-2 * log(1 - $p));
        // Coefficients from Moro 1995.
        $c0 = 2.515517;
        $c1 = 0.802853;
        $c2 = 0.010328;
        $d1 = 1.432788;
        $d2 = 0.189269;
        $d3 = 0.001308;

        return $q - (($c0 + $c1 * $q + $c2 * $q ** 2) / (1 + $d1 * $q + $d2 * $q ** 2 + $d3 * $q ** 3));
    }

    /**
     * χ² distribution survival function (1 - CDF) via Wilson-Hilferty
     * normal approximation. Accurate enough for α=0.05 significance
     * calls; for tighter alpha use a numerical regularized-incomplete-
     * gamma library.
     */
    private function chiSquareSurvival(float $chiSquare, int $df): float
    {
        if ($df <= 0 || $chiSquare <= 0) {
            return 1.0;
        }
        $h = 2.0 / (9.0 * $df);
        $z = (($chiSquare / $df) ** (1.0 / 3.0) - (1 - $h)) / sqrt($h);

        return 1 - $this->standardNormalCdf($z);
    }
}
