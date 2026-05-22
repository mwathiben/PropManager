<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Services\MetricsService;
use App\Services\RefundService;
use Illuminate\Console\Command;

/**
 * Phase-85 REFUND-RETRY-2: re-attempt refunds that failed BEFORE reaching the
 * gateway (reference-less), bounded by a retry cap. Refunds that failed after the
 * gateway accepted them (have a reference) are skipped + flagged needs_review by
 * RefundService::retry. Also emits the refunds_failed_count gauge.
 */
class RefundsRetryFailed extends Command
{
    private const RETRY_CAP = 3;

    protected $signature = 'refunds:retry-failed';

    protected $description = 'Phase-85 REFUND-RETRY-2: retry reference-less failed refunds + emit failed gauge.';

    public function handle(RefundService $service, MetricsService $metrics): int
    {
        $retried = 0;

        Refund::query()
            ->withoutGlobalScopes()
            ->where('status', RefundStatus::Failed)
            ->where('needs_review', false)
            ->where('retry_count', '<', self::RETRY_CAP)
            ->whereNull('paystack_refund_reference')
            ->whereNull('mpesa_conversation_id')
            ->chunkById(100, function ($refunds) use ($service, &$retried) {
                foreach ($refunds as $refund) {
                    $service->retry($refund);
                    $retried++;
                }
            });

        // Visibility gauge: refunds still failed (incl. needs_review) per landlord.
        $rows = Refund::query()
            ->withoutGlobalScopes()
            ->where('status', RefundStatus::Failed)
            ->selectRaw('landlord_id, COUNT(*) as cnt')
            ->groupBy('landlord_id')
            ->get();

        foreach ($rows as $row) {
            try {
                $metrics->gauge('refunds_failed_count', (float) $row->cnt, ['landlord_id' => (string) $row->landlord_id]);
            } catch (\Throwable) {
                // gauge emission is best-effort
            }
        }

        $this->info("refunds:retry-failed: {$retried} retried, {$rows->count()} landlord(s) with failed refunds");

        return self::SUCCESS;
    }
}
