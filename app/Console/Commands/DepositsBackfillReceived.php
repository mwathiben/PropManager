<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DepositTransaction;
use App\Models\Lease;
use App\Services\Finance\DepositSettlementService;
use Illuminate\Console\Command;

/**
 * Phase-81 DEPOSIT-SETTLEMENT-5: backfill the opening TYPE_RECEIVED deposit-ledger
 * entry for existing leases that hold a deposit but predate the journaling.
 * Idempotent — recordReceived() skips leases that already have one.
 */
class DepositsBackfillReceived extends Command
{
    protected $signature = 'deposits:backfill-received';

    protected $description = 'Phase-81: create the missing TYPE_RECEIVED deposit-ledger row for held deposits.';

    public function handle(DepositSettlementService $settlement): int
    {
        $existing = DepositTransaction::query()
            ->where('type', DepositTransaction::TYPE_RECEIVED)
            ->pluck('lease_id')
            ->all();

        $created = 0;
        Lease::query()
            ->withoutGlobalScopes()
            ->where('deposit_amount', '>', 0)
            ->whereNotIn('id', $existing)
            ->chunkById(200, function ($leases) use ($settlement, &$created) {
                foreach ($leases as $lease) {
                    if ($settlement->recordReceived($lease)) {
                        $created++;
                    }
                }
            });

        $this->info("deposits:backfill-received: {$created} received row(s) created");

        return self::SUCCESS;
    }
}
