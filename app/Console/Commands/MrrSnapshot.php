<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MrrSnapshot as MrrSnapshotModel;
use App\Models\SubscriptionPlan;
use App\Services\Growth\MrrSnapshotService;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Phase-34 GROWTH-MRR-2: daily MRR snapshot + Prometheus gauges.
 *
 * Emits:
 *   - mrr_total_kes                       (sum across plans)
 *   - mrr_by_plan_kes{plan_slug=X}        (per plan)
 *   - active_subscriptions_count{plan_slug=X}
 *
 * Scheduled 04:05 Africa/Nairobi — after Phase-33 log:volume-audit
 * 03:55, before the 04:30 backup window.
 */
class MrrSnapshot extends Command
{
    protected $signature = 'mrr:snapshot {--date= : YYYY-MM-DD; defaults to today}';

    protected $description = 'Phase-34 GROWTH-MRR-2: daily MRR snapshot + per-plan gauges.';

    public function handle(MrrSnapshotService $service, MetricsService $metrics): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $rows = $service->snapshotForDate($day);

        $total = 0.0;
        foreach ($rows as $row) {
            $plan = SubscriptionPlan::find($row->plan_id);
            $slug = $plan?->slug ?? (string) $row->plan_id;
            $metrics->gauge('mrr_by_plan_kes', (float) $row->mrr_kes, ['plan_slug' => $slug]);
            $metrics->gauge('active_subscriptions_count', (float) $row->active_subscriptions, ['plan_slug' => $slug]);
            $total += (float) $row->mrr_kes;
            $this->line(sprintf(
                '%s plan=%-12s mrr=%.2f active=%d new=%.2f churned=%.2f',
                $day->format('Y-m-d'), $slug, $row->mrr_kes, $row->active_subscriptions,
                $row->new_mrr_kes, $row->churned_mrr_kes,
            ));
        }

        $metrics->gauge('mrr_total_kes', round($total, 2));
        $this->info(sprintf('Snapshotted %d plan(s) for %s. Total MRR: KES %.2f', count($rows), $day->format('Y-m-d'), $total));

        return self::SUCCESS;
    }
}
