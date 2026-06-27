<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\DepositRefundPaid;
use App\Models\DepositRefundRequest;
use App\Models\MpesaB2cRequest;
use App\Models\PaymentConfiguration;
use App\Services\MpesaService;
use Illuminate\Console\Command;

/**
 * Phase-30 INT-MPESA-DEEP-2: silent-failure detector for in-flight
 * M-Pesa B2C payouts. Daraja's preferred path is the async
 * ResultURL callback — but a misconfigured callback URL, an
 * inbound firewall, or a quiet Daraja outage leaves a row stuck in
 * 'sent' forever. This command walks rows older than 5 minutes
 * still in 'sent' or 'queued' and asks Daraja for the canonical
 * transaction status, flipping the row + (if successful) the
 * linked DepositRefundRequest to PAID.
 *
 * Runs every 30 minutes in the workflow scheduler (Phase-29 cadence
 * + Africa/Nairobi + onOneServer).
 */
class MpesaReconcileStatus extends Command
{
    protected $signature = 'mpesa:reconcile-status
        {--landlord= : reconcile only this landlord_id}
        {--stale-minutes=5 : minimum age (minutes) of a row before polling}';

    protected $description = 'Phase-30 INT-MPESA-DEEP-2: poll Daraja for in-flight B2C transaction status.';

    public function handle(MpesaService $mpesa): int
    {
        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $cutoff = now()->subMinutes($staleMinutes);

        $query = MpesaB2cRequest::query()
            ->withoutGlobalScopes()
            ->whereIn('status', MpesaB2cRequest::OPEN_STATUSES)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_polled_at')->orWhere('last_polled_at', '<', $cutoff);
            })
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('sent_at')->orWhere('sent_at', '<', $cutoff);
            });

        if ($this->option('landlord')) {
            $query->where('landlord_id', (int) $this->option('landlord'));
        }

        $reconciled = 0;
        $confirmed = 0;
        $failed = 0;

        foreach ($query->cursor() as $row) {
            $counts = $this->reconcileRow($row, $mpesa);
            $reconciled += $counts['reconciled'];
            $confirmed += $counts['confirmed'];
            $failed += $counts['failed'];
        }

        $this->info("Reconciled: {$reconciled}, confirmed: {$confirmed}, failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * @return array{reconciled: int, confirmed: int, failed: int}
     */
    private function reconcileRow(MpesaB2cRequest $row, MpesaService $mpesa): array
    {
        $row->update(['last_polled_at' => now()]);

        if ($row->conversation_id === null) {
            return ['reconciled' => 0, 'confirmed' => 0, 'failed' => 0];
        }

        $config = PaymentConfiguration::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $row->landlord_id)
            ->first();
        if ($config === null || ! $config->hasMpesaApiConfig()) {
            return ['reconciled' => 0, 'confirmed' => 0, 'failed' => 0];
        }
        $mpesa->withConfig($config);

        $response = $mpesa->queryTransactionStatus($row->conversation_id);
        if ($response === null) {
            return ['reconciled' => 1, 'confirmed' => 0, 'failed' => 0];
        }

        $resultCode = $response['Result']['ResultCode'] ?? $response['ResultCode'] ?? null;
        $row->update(['last_response' => $response]);

        return $this->applyTransactionResult($row, $response, $resultCode);
    }

    /**
     * @return array{reconciled: int, confirmed: int, failed: int}
     */
    private function applyTransactionResult(MpesaB2cRequest $row, array $response, mixed $resultCode): array
    {
        if ((string) $resultCode === '0') {
            $row->update([
                'status' => MpesaB2cRequest::STATUS_SUCCEEDED,
                'transaction_id' => $response['Result']['TransactionID'] ?? $response['TransactionID'] ?? null,
                'confirmed_at' => now(),
            ]);
            $this->confirmLinkedRefund($row);

            return ['reconciled' => 1, 'confirmed' => 1, 'failed' => 0];
        }

        if ($resultCode !== null) {
            $row->update([
                'status' => MpesaB2cRequest::STATUS_FAILED,
                'failure_reason' => $response['Result']['ResultDesc'] ?? $response['ResultDesc'] ?? 'Daraja reported failure',
                'confirmed_at' => now(),
            ]);

            return ['reconciled' => 1, 'confirmed' => 0, 'failed' => 1];
        }

        return ['reconciled' => 1, 'confirmed' => 0, 'failed' => 0];
    }

    private function confirmLinkedRefund(MpesaB2cRequest $row): void
    {
        if ($row->source_type !== DepositRefundRequest::class) {
            return;
        }
        $refund = DepositRefundRequest::query()
            ->withoutGlobalScopes()
            ->find($row->source_id);
        if ($refund === null) {
            return;
        }
        if ($refund->status !== DepositRefundRequest::STATUS_APPROVED) {
            return;
        }
        $refund->update([
            'status' => DepositRefundRequest::STATUS_PAID,
            'payment_reference' => $row->transaction_id ?? $row->reference,
            'paid_at' => now(),
        ]);
        DepositRefundPaid::dispatch($refund);
    }
}
