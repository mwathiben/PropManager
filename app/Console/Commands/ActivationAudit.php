<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OnboardingMilestone;
use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Phase-31 ONB-TTFI-2: derive activation-funnel gauges from
 * onboarding_milestones. Emits one gauge per (milestone, period) +
 * two latency percentile gauges for the most actionable metric:
 * time from signed_up -> first_invoice.
 *
 * Same shape as Phase-29 workflow:health and Phase-30 bank-reconciliation:audit
 * audit pattern. Runs dailyAt 04:15 Africa/Nairobi onOneServer.
 */
class ActivationAudit extends Command
{
    protected $signature = 'activation:audit';

    protected $description = 'Phase-31 ONB-TTFI-2: emit activation funnel Prometheus gauges.';

    public const PERIODS = ['24h', '7d', '30d'];

    public function handle(MetricsService $metrics): int
    {
        $now = now();

        $signupsByPeriod = [];
        foreach (self::PERIODS as $period) {
            $signupsByPeriod[$period] = User::query()
                ->where('role', 'landlord')
                ->where('created_at', '>=', $this->cutoffFor($period, $now))
                ->count();
            $metrics->gauge('activation_signups_count', (float) $signupsByPeriod[$period], ['period' => $period]);
        }

        foreach (OnboardingMilestone::FUNNEL as $milestone) {
            foreach (self::PERIODS as $period) {
                $count = OnboardingMilestone::query()
                    ->where('milestone', $milestone)
                    ->where('reached_at', '>=', $this->cutoffFor($period, $now))
                    ->count();
                $metrics->gauge('activation_milestone_count', (float) $count, [
                    'milestone' => $milestone,
                    'period' => $period,
                ]);
            }
        }

        $ttfiHours = $this->timeToFirstInvoiceHours();
        if ($ttfiHours !== []) {
            $metrics->gauge('activation_time_to_first_invoice_p50_hours', $this->percentile($ttfiHours, 0.5));
            $metrics->gauge('activation_time_to_first_invoice_p90_hours', $this->percentile($ttfiHours, 0.9));
        }

        $this->info(sprintf(
            'Signups 24h=%d 7d=%d 30d=%d. TTFI samples=%d.',
            $signupsByPeriod['24h'],
            $signupsByPeriod['7d'],
            $signupsByPeriod['30d'],
            count($ttfiHours),
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<float>
     */
    private function timeToFirstInvoiceHours(): array
    {
        $signups = OnboardingMilestone::query()
            ->where('milestone', OnboardingMilestone::SIGNED_UP)
            ->pluck('reached_at', 'landlord_id')
            ->all();
        if ($signups === []) {
            return [];
        }
        $firstInvoices = OnboardingMilestone::query()
            ->where('milestone', OnboardingMilestone::FIRST_INVOICE)
            ->whereIn('landlord_id', array_keys($signups))
            ->pluck('reached_at', 'landlord_id')
            ->all();
        $deltas = [];
        foreach ($firstInvoices as $landlordId => $invoiceAt) {
            $signedAt = $signups[$landlordId] ?? null;
            if ($signedAt === null) {
                continue;
            }
            $deltas[] = (float) Carbon::parse($signedAt)
                ->diffInMinutes(Carbon::parse($invoiceAt)) / 60.0;
        }

        return $deltas;
    }

    private function percentile(array $values, float $p): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $idx = (int) floor($p * (count($values) - 1));

        return (float) $values[$idx];
    }

    private function cutoffFor(string $period, Carbon $now): Carbon
    {
        return match ($period) {
            '24h' => $now->copy()->subHours(24),
            '7d' => $now->copy()->subDays(7),
            '30d' => $now->copy()->subDays(30),
            default => $now->copy()->subDay(),
        };
    }
}
