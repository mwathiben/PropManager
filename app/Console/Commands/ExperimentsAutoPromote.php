<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\ExperimentConcluded;
use App\Models\Experiment;
use App\Models\ExperimentExposure;
use App\Models\ProductEvent;
use App\Services\Platform\ExperimentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-56 AB-AUTO-PROMOTE-1: nightly cron that flips RUNNING experiments
 * to CONCLUDED when the dual chi-square + Bayesian significance gate
 * passes.
 *
 * Gate (must satisfy BOTH):
 *   - chi-square p_value < 0.01  (variants are genuinely different)
 *   - bayes  p_b_better_than_a > 0.95 OR < 0.05  (one variant wins)
 *
 * Success closure built per-experiment:
 *   - experiments.success_event_name set → "user had this specific
 *     event_name after exposure.fired_at"
 *   - NULL                              → "user had ANY product_event
 *     after exposure.fired_at"
 *
 * The 2-arm assumption matches computeBayesianPosterior's requirement.
 * Multi-arm experiments are eligible for chi-square but the cron skips
 * promotion since Bayesian requires exactly 2 variants — operator
 * concludes those manually.
 */
class ExperimentsAutoPromote extends Command
{
    protected $signature = 'experiments:auto-promote';

    protected $description = 'Auto-promote winning variant when chi-square + Bayesian significance both pass.';

    public const CHI_THRESHOLD = 0.01;

    public const BAYES_WIN_THRESHOLD = 0.95;

    public function handle(ExperimentService $service): int
    {
        $running = Experiment::query()
            ->where('status', Experiment::STATUS_RUNNING)
            ->get();

        $promoted = 0;
        foreach ($running as $experiment) {
            $variants = $experiment->variants ?? [];
            if (count($variants) !== 2) {
                continue;
            }

            $successClosure = $this->buildSuccessClosure($experiment);

            try {
                $chi = $service->computeChiSquareSignificance($experiment->experiment_key, $successClosure);
                $bayes = $service->computeBayesianPosterior($experiment->experiment_key, $successClosure, samples: 10000);
            } catch (\Throwable $e) {
                $this->warn("Skipped {$experiment->experiment_key}: {$e->getMessage()}");

                continue;
            }

            if ((float) $chi['p_value'] >= self::CHI_THRESHOLD) {
                continue;
            }

            $bWinsProb = (float) $bayes['p_b_better_than_a'];
            $winner = match (true) {
                $bWinsProb > self::BAYES_WIN_THRESHOLD => $bayes['variant_b'],
                $bWinsProb < (1.0 - self::BAYES_WIN_THRESHOLD) => $bayes['variant_a'],
                default => null,
            };

            if ($winner === null) {
                continue;
            }

            DB::transaction(function () use ($experiment, $winner) {
                $experiment->update([
                    'status' => Experiment::STATUS_CONCLUDED,
                    'winning_variant_key' => $winner,
                    'ends_at' => now(),
                ]);
            });

            ExperimentConcluded::dispatch(
                $experiment->experiment_key,
                (string) $winner,
                (float) $chi['p_value'],
                $bWinsProb,
            );

            $promoted++;
            $this->info("Promoted {$experiment->experiment_key} → {$winner} (chi p={$chi['p_value']}, bayes={$bWinsProb}).");
        }

        $this->info("Auto-promote sweep complete: {$promoted} experiment(s) concluded.");

        return self::SUCCESS;
    }

    private function buildSuccessClosure(Experiment $experiment): \Closure
    {
        $eventName = $experiment->success_event_name;

        return function (int $userId) use ($experiment, $eventName): bool {
            $exposure = ExperimentExposure::query()
                ->where('experiment_key', $experiment->experiment_key)
                ->where('user_id', $userId)
                ->first();

            if ($exposure === null) {
                return false;
            }

            $query = ProductEvent::query()->withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('created_at', '>=', $exposure->fired_at);

            if ($eventName !== null) {
                $query->where('event_name', $eventName);
            }

            return $query->exists();
        };
    }
}
