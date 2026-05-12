<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase-17 MONEY-5: detect invoice.amount_paid mismatches with
 * sum(payments.amount WHERE invoice_id=N).
 *
 * The Phase-4 unique index on wallet_transactions(payment_id, type)
 * prevents wallet double-credit. There is no DB-level constraint
 * enforcing the AGGREGATE invariant `sum(payment) <= invoice.total_due`
 * — MySQL CHECK constraints can't see aggregate state. This command
 * is the periodic reconciliation that surfaces any drift to ops.
 *
 * Wired into routes/console.php as a nightly job. Mismatches are
 * logged to the schedule channel + a Prometheus counter is bumped
 * so a long-term trend is graphable.
 */
class AuditPaymentAllocations extends Command
{
    protected $signature = 'payments:audit-allocations {--limit=100 : max mismatches to report}';

    protected $description = 'Phase-17 MONEY-5: detect invoice.amount_paid drift vs sum(payments).';

    public function handle(MetricsService $metrics): int
    {
        $rows = DB::table('invoices')
            ->leftJoin('payments', 'payments.invoice_id', '=', 'invoices.id')
            ->whereNull('invoices.deleted_at')
            ->select(
                'invoices.id as invoice_id',
                'invoices.landlord_id',
                'invoices.amount_paid as recorded_amount_paid',
                DB::raw('COALESCE(SUM(payments.amount), 0) as actual_payment_sum'),
            )
            ->groupBy('invoices.id', 'invoices.landlord_id', 'invoices.amount_paid')
            ->havingRaw('ABS(invoices.amount_paid - COALESCE(SUM(payments.amount), 0)) > 0.01')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('payments:audit-allocations: 0 invoices with amount_paid drift.');

            try {
                $metrics->gauge('invoice_amount_paid_drift_count', 0.0);
            } catch (\Throwable) {
            }

            return self::SUCCESS;
        }

        $this->warn("payments:audit-allocations: {$rows->count()} invoices with amount_paid drift > 0.01:");
        foreach ($rows as $row) {
            $delta = (float) $row->actual_payment_sum - (float) $row->recorded_amount_paid;
            $this->warn(sprintf(
                '  invoice_id=%d landlord_id=%d recorded=%.2f actual_sum=%.2f delta=%.2f',
                $row->invoice_id,
                $row->landlord_id,
                $row->recorded_amount_paid,
                $row->actual_payment_sum,
                $delta,
            ));
        }

        Log::channel(config('logging.schedule_channel', 'stack'))->warning(
            'payments:audit-allocations detected drift',
            [
                'count' => $rows->count(),
                'sample' => $rows->take(10)->map(fn ($r) => (array) $r)->all(),
            ]
        );

        try {
            $metrics->gauge('invoice_amount_paid_drift_count', (float) $rows->count());
        } catch (\Throwable) {
        }

        return self::FAILURE;
    }
}
