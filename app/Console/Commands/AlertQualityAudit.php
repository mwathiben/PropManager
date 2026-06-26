<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AlertFiring;
use App\Services\MetricsService;
use App\Services\Sre\AlertRegistry;
use Illuminate\Console\Command;

/**
 * Phase-32 SRE-ALERT-2: signal-to-noise scoring per alert key.
 *
 *   signal_count = firings resolved AFTER acknowledgement (operator
 *                  validated) OR firings where duration > 5 minutes
 *   noise_count  = firings resolved within 5 minutes WITHOUT
 *                  acknowledgement (auto-recovered blip)
 *   ratio        = signal / (signal + noise)
 *
 * A ratio below 0.5 means the alert is producing more noise than
 * signal — fatigue territory. Emits alert_signal_to_noise_ratio
 * gauges + alert_fatigue_count for the operator dashboard.
 */
class AlertQualityAudit extends Command
{
    protected $signature = 'alert:quality {--days=30 : rolling window for the ratio computation}';

    protected $description = 'Phase-32 SRE-ALERT-2: per-alert signal-to-noise ratio + fatigue counter.';

    public const NOISE_RESOLVE_WINDOW_MINUTES = 5;

    public const FATIGUE_RATIO_THRESHOLD = 0.5;

    public function handle(AlertRegistry $registry, MetricsService $metrics): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));
        $fatigue = 0;

        foreach ($registry->all() as $alert) {
            $key = $alert['key'];
            $firings = AlertFiring::query()
                ->where('alert_key', $key)
                ->where('fired_at', '>=', $cutoff)
                ->get();

            if ($firings->isEmpty()) {
                $metrics->gauge('alert_signal_to_noise_ratio', 1.0, ['alert_key' => $key]);

                continue;
            }

            [$signal, $noise] = $this->classifyFirings($firings);
            $total = $signal + $noise;
            $ratio = $total > 0 ? round($signal / $total, 3) : 1.0;
            $metrics->gauge('alert_signal_to_noise_ratio', $ratio, ['alert_key' => $key]);

            if ($ratio < self::FATIGUE_RATIO_THRESHOLD) {
                $fatigue++;
            }

            $this->line(sprintf('%-40s ratio=%.3f signal=%d noise=%d', $key, $ratio, $signal, $noise));
        }

        $metrics->gauge('alert_fatigue_count', (float) $fatigue);
        $this->info("Alerts in fatigue territory: {$fatigue}");

        return self::SUCCESS;
    }

    /**
     * @return array{int, int} [signal_count, noise_count]
     */
    private function classifyFirings(\Illuminate\Support\Collection $firings): array
    {
        $signal = 0;
        $noise = 0;

        foreach ($firings as $firing) {
            if ($firing->acknowledged_at !== null) {
                $signal++;

                continue;
            }

            if ($firing->resolved_at === null) {
                $signal++;

                continue;
            }

            $durationMin = abs($firing->fired_at->diffInMinutes($firing->resolved_at));

            if ($durationMin > self::NOISE_RESOLVE_WINDOW_MINUTES) {
                $signal++;
            } else {
                $noise++;
            }
        }

        return [$signal, $noise];
    }
}
