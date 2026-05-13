<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase-19 INDEX-1 (DATA-4 closure): detect invoice.late_fees_total
 * mismatches with sum(late_fees.fee_amount WHERE is_waived=false) per
 * invoice. LateFee does not soft-delete; the active subset is
 * `is_waived = false` (waived rows stay in the table for audit but
 * don't contribute to the cached total).
 *
 * Same mechanism as Phase-17 MONEY-5 payments:audit-allocations but
 * for the late-fees denormalization. invoice.late_fees_total is a
 * cached aggregate maintained by Invoice::recalculateLateFees; if a
 * future bug bypasses the recalc (e.g. a LateFee::waive that forgets
 * to touch the parent), the cached value drifts silently until a
 * report shows the wrong total_due.
 *
 * Wired into routes/console.php at 05:40 Africa/Nairobi (10min after
 * MONEY-5's 05:30). Logs mismatches to the schedule channel + bumps
 * the invoice_late_fees_total_drift_count Prometheus gauge so the
 * Phase-14/16 ops dashboards alert on sustained drift.
 */
class AuditLateFeesAllocation extends Command
{
    protected $signature = 'latefees:audit-drift {--limit=100 : max mismatches to report}';

    protected $description = 'Phase-19 INDEX-1: detect invoice.late_fees_total drift vs sum(active late_fees).';

    public function handle(MetricsService $metrics): int
    {
        $rows = DB::table('invoices')
            ->leftJoin('late_fees', function ($join) {
                $join->on('late_fees.invoice_id', '=', 'invoices.id')
                    ->where('late_fees.is_waived', '=', false);
            })
            ->whereNull('invoices.deleted_at')
            ->select(
                'invoices.id as invoice_id',
                'invoices.landlord_id',
                'invoices.late_fees_total as recorded_total',
                DB::raw('COALESCE(SUM(late_fees.fee_amount), 0) as actual_active_sum'),
            )
            ->groupBy('invoices.id', 'invoices.landlord_id', 'invoices.late_fees_total')
            ->havingRaw('ABS(invoices.late_fees_total - COALESCE(SUM(late_fees.fee_amount), 0)) > 0.01')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('latefees:audit-drift: 0 invoices with late_fees_total drift.');

            try {
                $metrics->gauge('invoice_late_fees_total_drift_count', 0.0);
            } catch (\Throwable) {
            }

            return self::SUCCESS;
        }

        $this->warn("latefees:audit-drift: {$rows->count()} invoices with late_fees_total drift > 0.01:");
        foreach ($rows as $row) {
            $delta = (float) $row->actual_active_sum - (float) $row->recorded_total;
            $this->warn(sprintf(
                '  invoice_id=%d landlord_id=%d recorded=%.2f active_sum=%.2f delta=%.2f',
                $row->invoice_id,
                $row->landlord_id,
                $row->recorded_total,
                $row->actual_active_sum,
                $delta,
            ));
        }

        Log::channel(config('logging.schedule_channel', 'stack'))->warning(
            'latefees:audit-drift detected drift',
            [
                'count' => $rows->count(),
                'sample' => $rows->take(10)->map(fn ($r) => (array) $r)->all(),
            ]
        );

        try {
            $metrics->gauge('invoice_late_fees_total_drift_count', (float) $rows->count());
        } catch (\Throwable) {
        }

        return self::FAILURE;
    }
}
