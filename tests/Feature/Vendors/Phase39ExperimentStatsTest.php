<?php

declare(strict_types=1);

namespace Tests\Feature\Vendors;

use App\Models\Experiment;
use App\Models\ExperimentExposure;
use App\Services\Platform\ExperimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-39 EXP-STATS-2-1/2/3: multi-variant chi-square +
 * Bayesian beta posterior + O'Brien-Fleming alpha-spending.
 */
class Phase39ExperimentStatsTest extends TestCase
{
    use RefreshDatabase;

    private function seedExposures(string $key, array $variantSampleSizes, array $successesByVariant): void
    {
        $userId = 100000;
        foreach ($variantSampleSizes as $variant => $n) {
            for ($i = 0; $i < $n; $i++) {
                ExperimentExposure::create([
                    'user_id' => $userId++,
                    'experiment_key' => $key,
                    'variant_key' => $variant,
                    'fired_at' => now(),
                ]);
            }
        }
    }

    public function test_chi_square_detects_significant_difference_in_3_arms(): void
    {
        Experiment::create([
            'experiment_key' => 'three_arm',
            'name' => '3-arm test',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [
                ['key' => 'control', 'weight' => 33],
                ['key' => 'green', 'weight' => 33],
                ['key' => 'blue', 'weight' => 34],
            ],
        ]);

        $userId = 200000;
        $controlIds = [];
        $greenIds = [];
        $blueIds = [];
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId, 'experiment_key' => 'three_arm', 'variant_key' => 'control', 'fired_at' => now(),
            ]);
            if ($i < 50) $controlIds[$userId] = true;
            $userId++;
        }
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId, 'experiment_key' => 'three_arm', 'variant_key' => 'green', 'fired_at' => now(),
            ]);
            if ($i < 90) $greenIds[$userId] = true;
            $userId++;
        }
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId, 'experiment_key' => 'three_arm', 'variant_key' => 'blue', 'fired_at' => now(),
            ]);
            if ($i < 100) $blueIds[$userId] = true;
            $userId++;
        }

        $isSuccess = fn (int $uid) => isset($controlIds[$uid]) || isset($greenIds[$uid]) || isset($blueIds[$uid]);
        $result = app(ExperimentService::class)->computeChiSquareSignificance('three_arm', $isSuccess);

        $this->assertSame(['control', 'green', 'blue'], $result['variants']);
        $this->assertSame(2, $result['df']);
        $this->assertGreaterThan(0, $result['chi_square']);
        $this->assertLessThan(0.05, $result['p_value']);
        $this->assertTrue($result['is_significant']);
    }

    public function test_chi_square_returns_not_significant_for_tied_variants(): void
    {
        Experiment::create([
            'experiment_key' => 'tied_arms',
            'name' => 'tied',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [
                ['key' => 'a', 'weight' => 50],
                ['key' => 'b', 'weight' => 50],
            ],
        ]);

        $userId = 300000;
        $hits = [];
        foreach (['a', 'b'] as $v) {
            for ($i = 0; $i < 1000; $i++) {
                ExperimentExposure::create([
                    'user_id' => $userId, 'experiment_key' => 'tied_arms', 'variant_key' => $v, 'fired_at' => now(),
                ]);
                if ($i < 50) $hits[$userId] = true;
                $userId++;
            }
        }

        $isSuccess = fn (int $uid) => isset($hits[$uid]);
        $result = app(ExperimentService::class)->computeChiSquareSignificance('tied_arms', $isSuccess);

        $this->assertGreaterThan(0.5, $result['p_value']);
        $this->assertFalse($result['is_significant']);
    }

    public function test_chi_square_throws_for_single_variant(): void
    {
        Experiment::create([
            'experiment_key' => 'single',
            'name' => 'single',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [['key' => 'control', 'weight' => 100]],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(ExperimentService::class)->computeChiSquareSignificance('single', fn () => false);
    }

    public function test_bayesian_posterior_finds_b_better_for_47_vs_71_per_1000(): void
    {
        Experiment::create([
            'experiment_key' => 'bayes_classic',
            'name' => 'bayesian classic',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [
                ['key' => 'control', 'weight' => 50],
                ['key' => 'treatment', 'weight' => 50],
            ],
        ]);

        $userId = 400000;
        $hits = [];
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId, 'experiment_key' => 'bayes_classic', 'variant_key' => 'control', 'fired_at' => now(),
            ]);
            if ($i < 47) $hits[$userId] = true;
            $userId++;
        }
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId, 'experiment_key' => 'bayes_classic', 'variant_key' => 'treatment', 'fired_at' => now(),
            ]);
            if ($i < 71) $hits[$userId] = true;
            $userId++;
        }

        $isSuccess = fn (int $uid) => isset($hits[$uid]);
        // Use 10000 samples in test for speed — still tight enough.
        $result = app(ExperimentService::class)->computeBayesianPosterior('bayes_classic', $isSuccess, 10000);

        $this->assertGreaterThan(0.95, $result['p_b_better_than_a']);
        $this->assertGreaterThan(0, $result['expected_lift_pct']);
        $this->assertLessThan($result['ci_95_high'], $result['ci_95_low']);
    }

    public function test_alpha_spending_boundary_decreases_with_more_peeks(): void
    {
        $service = app(ExperimentService::class);
        $earlyBoundary = $service->computeAlphaSpendingBoundary(1, 10);
        $finalBoundary = $service->computeAlphaSpendingBoundary(10, 10);

        // Early peek requires a much-larger boundary; final peek
        // approaches the standard z_{0.025} ≈ 1.96.
        $this->assertGreaterThan($finalBoundary, $earlyBoundary);
        $this->assertEqualsWithDelta(1.96, $finalBoundary, 0.1);
    }

    public function test_alpha_spending_boundary_rejects_out_of_range_peek(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ExperimentService::class)->computeAlphaSpendingBoundary(15, 10);
    }
}
