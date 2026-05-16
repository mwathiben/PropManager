<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OnboardingProgress;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-31 ONB-WIZARD-3: silent-failure detector for users stalled
 * mid-wizard. Buckets stalled (is_complete=false) progress rows by
 * days since last_touched_at and emits per-bucket gauges so Grafana
 * can alert when the long-tail bucket (30+ days) climbs.
 *
 * Same shape as Phase-29 workflow:health + Phase-30 bank-reconciliation:audit
 * audit pattern. Runs dailyAt 04:45 Africa/Nairobi onOneServer.
 */
class OnboardingWizardAudit extends Command
{
    protected $signature = 'onboarding-wizard:audit';

    protected $description = 'Phase-31 ONB-WIZARD-3: bucket stalled onboarding wizards by days inactive.';

    public const BUCKETS = ['1-3', '4-7', '8-30', '30+'];

    public function handle(MetricsService $metrics): int
    {
        $now = now();
        $buckets = array_fill_keys(self::BUCKETS, 0);

        OnboardingProgress::query()
            ->where('is_complete', false)
            ->cursor()
            ->each(function (OnboardingProgress $row) use (&$buckets, $now): void {
                $touched = $row->last_touched_at ?? $row->started_at ?? $row->created_at;
                if ($touched === null) {
                    return;
                }
                $days = (int) $touched->diffInDays($now);
                if ($days <= 3) {
                    $buckets['1-3']++;
                } elseif ($days <= 7) {
                    $buckets['4-7']++;
                } elseif ($days <= 30) {
                    $buckets['8-30']++;
                } else {
                    $buckets['30+']++;
                }
            });

        foreach ($buckets as $bucket => $count) {
            $metrics->gauge('onboarding_stalled_count', (float) $count, ['bucket' => $bucket]);
            $this->line(sprintf('bucket=%-5s count=%d', $bucket, $count));
        }

        DB::connection()->disconnect();

        return self::SUCCESS;
    }
}
