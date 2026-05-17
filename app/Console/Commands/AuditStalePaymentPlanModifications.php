<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentPlanModification;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-45 PAY-PLAN-MOD-3: emit payment_plan_modification_pending_24h
 * gauges so oncall can spot landlords who have ghosted a tenant's
 * modification request. Sev4 opens at 7-day threshold via the
 * alert-thresholds.md row.
 *
 * Cron: daily 06:15 Africa/Nairobi.
 */
class AuditStalePaymentPlanModifications extends Command
{
    protected $signature = 'payment-plans:audit-stale-modifications';

    protected $description = 'Phase-45 PAY-PLAN-MOD-3: emit gauges for pending modifications >24h old.';

    public function handle(MetricsService $metrics): int
    {
        $cutoff24h = now()->subDay();
        $stale = PaymentPlanModification::query()
            ->where('status', PaymentPlanModification::STATUS_PENDING)
            ->where('created_at', '<', $cutoff24h)
            ->get();

        foreach ($stale as $row) {
            $metrics->gauge(
                'payment_plan_modification_pending_24h',
                $row->created_at->diffInDays(now()),
                ['plan_id' => (string) $row->payment_plan_id],
            );
        }

        $this->info(sprintf('Emitted gauge for %d stale modifications.', $stale->count()));

        return self::SUCCESS;
    }
}
