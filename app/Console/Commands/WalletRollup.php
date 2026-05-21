<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Currency;
use App\Models\CreditNote;
use App\Models\LeaseWalletBalance;
use App\Models\PaymentConfiguration;
use App\Models\WalletTransaction;
use App\Services\MetricsService;
use App\Services\WorkflowLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase-76 WALLET-DEEP CI-1: daily wallet observability. Emits standing tenant
 * credit float + credit-note backlog so finance ops isn't blind to held credit.
 *
 * Scheduled daily 05:25 Africa/Nairobi (after wallet:auto-apply 05:15 so the
 * gauge reflects post-sweep balances).
 */
class WalletRollup extends Command
{
    protected $signature = 'wallet:rollup';

    protected $description = 'Phase-76 CI-1: emit wallet credit-balance + credit-note backlog gauges.';

    public function handle(MetricsService $metrics, WorkflowLogger $workflowLogger): int
    {
        $emitted = 0;

        try {
            // Default-currency credit lives in the leases.wallet_balance scalar.
            $defaultByLandlord = DB::table('leases')
                ->whereNull('deleted_at')
                ->where('wallet_balance', '!=', 0)
                ->groupBy('landlord_id')
                ->selectRaw('landlord_id, SUM(wallet_balance) as total')
                ->orderByDesc('total')
                ->limit(50)
                ->get();

            foreach ($defaultByLandlord as $row) {
                $currency = (PaymentConfiguration::where('landlord_id', $row->landlord_id)->value('default_currency') ?? Currency::default());
                $code = $currency instanceof Currency ? $currency->value : (string) $currency;
                $metrics->gauge('wallet_total_credit_balance', (float) $row->total, [
                    'landlord_id' => (string) $row->landlord_id,
                    'currency' => $code,
                ]);
                $emitted++;
            }

            // Non-default currencies live in lease_wallet_balances.
            LeaseWalletBalance::query()
                ->where('balance', '!=', 0)
                ->groupBy('landlord_id', 'currency')
                ->selectRaw('landlord_id, currency, SUM(balance) as total')
                ->limit(50)
                ->get()
                ->each(function ($row) use ($metrics, &$emitted) {
                    $metrics->gauge('wallet_total_credit_balance', (float) $row->total, [
                        'landlord_id' => (string) $row->landlord_id,
                        'currency' => (string) $row->currency,
                    ]);
                    $emitted++;
                });

            CreditNote::query()
                ->where('status', CreditNote::STATUS_PENDING)
                ->groupBy('landlord_id')
                ->selectRaw('landlord_id, COUNT(*) as cnt')
                ->orderByDesc('cnt')
                ->limit(50)
                ->get()
                ->each(function ($row) use ($metrics, &$emitted) {
                    $metrics->gauge('credit_notes_pending_count', (float) $row->cnt, [
                        'landlord_id' => (string) $row->landlord_id,
                    ]);
                    $emitted++;
                });

            // Wallet credit applied to invoices in the last 24h (sweep + tenant + auto).
            WalletTransaction::query()
                ->where('type', 'debit')
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('landlord_id')
                ->selectRaw('landlord_id, COUNT(*) as cnt')
                ->limit(50)
                ->get()
                ->each(function ($row) use ($metrics, &$emitted) {
                    $metrics->gauge('wallet_applied_24h_count', (float) $row->cnt, [
                        'landlord_id' => (string) $row->landlord_id,
                    ]);
                    $emitted++;
                });
        } catch (\Throwable $e) {
            Log::warning('wallet:rollup gauge emit failed', ['error' => $e->getMessage()]);
        }

        $this->info("wallet:rollup: {$emitted} gauge(s) emitted");

        $workflowLogger->log(
            workflowName: 'wallet:rollup',
            action: 'completed',
            metadata: ['gauges' => $emitted],
        );

        return self::SUCCESS;
    }
}
