<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BankWebhookLog;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-30 INT-BANK-PARITY-3: unified nightly drift audit across every
 * bank integration. Emits per-bank gauges so an upstream Prometheus
 * alert can fire when:
 *
 *   - bank_webhook_unmatched_count{bank=X} climbs (incoming credit
 *     alerts that never matched an invoice — usually a misconfigured
 *     bank_account_number on PaymentConfiguration).
 *   - bank_webhook_error_count{bank=X} climbs (signature failures /
 *     parse errors — usually a credential rotation or schema change).
 *   - bank_webhook_silence_hours{bank=X} > 48 (no inbound webhook
 *     for two days — the bank's outbound queue may be wedged).
 *
 * Same shape as Phase-29 workflow:health silent-failure detector +
 * Phase-22 MetricsService::gauge continuous-time-series pattern.
 */
class BankReconciliationAudit extends Command
{
    protected $signature = 'bank-reconciliation:audit {--window=24 : recency window (hours) for unmatched + error counts}';

    protected $description = 'Phase-30 INT-BANK-PARITY-3: per-bank drift audit + Prometheus gauges.';

    public const SUPPORTED_BANKS = ['equity', 'kcb', 'coop', 'postbank', 'familybank'];

    public function handle(MetricsService $metrics): int
    {
        $hours = max(1, (int) $this->option('window'));
        $since = now()->subHours($hours);

        foreach (self::SUPPORTED_BANKS as $bank) {
            $unmatched = BankWebhookLog::query()
                ->where('bank_code', $bank)
                ->where('status', 'success')
                ->where('created_at', '>=', $since)
                ->whereNull('processed_payment_id')
                ->count();

            $errors = BankWebhookLog::query()
                ->where('bank_code', $bank)
                ->where('status', 'error')
                ->where('created_at', '>=', $since)
                ->count();

            $lastSeenAt = BankWebhookLog::query()
                ->where('bank_code', $bank)
                ->max('created_at');

            $silenceHours = $lastSeenAt === null
                ? 999.0
                : round(now()->diffInMinutes($lastSeenAt) / 60.0, 1);

            $metrics->gauge('bank_webhook_unmatched_count', (float) $unmatched, ['bank' => $bank]);
            $metrics->gauge('bank_webhook_error_count', (float) $errors, ['bank' => $bank]);
            $metrics->gauge('bank_webhook_silence_hours', $silenceHours, ['bank' => $bank]);

            $this->line(sprintf(
                '%-12s unmatched=%d errors=%d silence_hours=%s',
                $bank,
                $unmatched,
                $errors,
                $silenceHours,
            ));
        }

        unset($since);
        DB::connection()->disconnect();

        return self::SUCCESS;
    }
}
