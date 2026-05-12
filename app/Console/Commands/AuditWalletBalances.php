<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase-18 DATA-2: detect Lease.wallet_balance vs sum(wallet_transactions)
 * drift.
 *
 * Phase-4 CONC-2 + the wallet_transactions(payment_id, type) unique
 * index prevent double-credit at the ROW layer. There is no DB-level
 * enforcement of the AGGREGATE invariant
 *   Lease.wallet_balance == SUM(credits) - SUM(debits)
 * for a given lease_id. This command is the periodic reconciliation
 * that surfaces any drift to ops, same shape as Phase-17 MONEY-5's
 * payments:audit-allocations.
 *
 * Scheduled nightly at 05:35 Africa/Nairobi via routes/console.php.
 * Exits FAILURE on drift > 0.01 KES so an alerting policy can hook
 * the run. The lease_wallet_balance_drift_count Prometheus gauge
 * surfaces the count for Grafana time-series.
 */
class AuditWalletBalances extends Command
{
    protected $signature = 'wallets:audit-balances {--limit=100 : max mismatches to report}';

    protected $description = 'Phase-18 DATA-2: detect Lease.wallet_balance drift vs sum(wallet_transactions).';

    public function handle(MetricsService $metrics): int
    {
        // Sum credits and debits separately, then compare net against
        // recorded wallet_balance. Filter to non-deleted leases.
        $rows = DB::table('leases')
            ->leftJoin('wallet_transactions', 'wallet_transactions.lease_id', '=', 'leases.id')
            ->whereNull('leases.deleted_at')
            ->select(
                'leases.id as lease_id',
                'leases.landlord_id',
                'leases.wallet_balance as recorded_wallet_balance',
                DB::raw("COALESCE(SUM(CASE WHEN wallet_transactions.type = 'credit' THEN wallet_transactions.amount ELSE 0 END), 0) as credit_sum"),
                DB::raw("COALESCE(SUM(CASE WHEN wallet_transactions.type = 'debit' THEN wallet_transactions.amount ELSE 0 END), 0) as debit_sum"),
            )
            ->groupBy('leases.id', 'leases.landlord_id', 'leases.wallet_balance')
            ->havingRaw('ABS(leases.wallet_balance - (COALESCE(SUM(CASE WHEN wallet_transactions.type = "credit" THEN wallet_transactions.amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN wallet_transactions.type = "debit" THEN wallet_transactions.amount ELSE 0 END), 0))) > 0.01')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('wallets:audit-balances: 0 leases with wallet_balance drift.');

            try {
                $metrics->gauge('lease_wallet_balance_drift_count', 0.0);
            } catch (\Throwable) {
            }

            return self::SUCCESS;
        }

        $this->warn("wallets:audit-balances: {$rows->count()} leases with wallet_balance drift > 0.01:");
        foreach ($rows as $row) {
            $netSum = (float) $row->credit_sum - (float) $row->debit_sum;
            $delta = $netSum - (float) $row->recorded_wallet_balance;
            $this->warn(sprintf(
                '  lease_id=%d landlord_id=%d recorded=%.2f net_sum=%.2f delta=%.2f',
                $row->lease_id,
                $row->landlord_id,
                $row->recorded_wallet_balance,
                $netSum,
                $delta,
            ));
        }

        Log::channel(config('logging.schedule_channel', 'stack'))->warning(
            'wallets:audit-balances detected drift',
            [
                'count' => $rows->count(),
                'sample' => $rows->take(10)->map(fn ($r) => (array) $r)->all(),
            ]
        );

        try {
            $metrics->gauge('lease_wallet_balance_drift_count', (float) $rows->count());
        } catch (\Throwable) {
        }

        return self::FAILURE;
    }
}
