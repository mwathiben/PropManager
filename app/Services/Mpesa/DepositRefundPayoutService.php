<?php

declare(strict_types=1);

namespace App\Services\Mpesa;

use App\Models\DepositRefundRequest;
use App\Models\MpesaB2cRequest;
use App\Models\PaymentConfiguration;
use App\Services\MpesaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase-30 INT-MPESA-DEEP-1: turn an approved DepositRefundRequest
 * into an M-Pesa B2C payout, idempotently. The flow:
 *
 *   1. Cache::add() on a per-refund key — collapses double-clicks
 *      and double-submits to a single Daraja call.
 *   2. Format the phone (254XXXXXXXXX), build the originator
 *      conversation id, persist the MpesaB2cRequest in 'queued'.
 *   3. Call MpesaService::initiateB2C; on Daraja-accepted (status
 *      'sent') we hold and wait for the ResultURL callback or the
 *      mpesa:reconcile-status poll to flip to 'succeeded'.
 *   4. The DepositRefundRequest itself only flips to PAID once the
 *      B2C confirms — until then it stays in APPROVED so a
 *      reviewer can see "payout in flight" via the linked B2C row.
 */
class DepositRefundPayoutService
{
    public function __construct(
        private readonly MpesaService $mpesa,
    ) {}

    public function payout(DepositRefundRequest $refund, string $phone): MpesaB2cRequest
    {
        if ($refund->status !== DepositRefundRequest::STATUS_APPROVED) {
            throw new \DomainException('Only approved refunds can be paid via M-Pesa B2C.');
        }
        $amountCents = (int) ($refund->final_amount_cents ?? 0);
        if ($amountCents <= 0) {
            throw new \DomainException('Refund has no final_amount_cents.');
        }

        [$row, $isNew] = $this->reservePayoutRow($refund, $phone, $amountCents);

        // An open payout already exists — the money is already in flight,
        // so do NOT fire a second B2C call.
        if (! $isNew) {
            return $row;
        }

        // The payout row is now durably committed. The real money movement is
        // fired only AFTER commit, so a rolled-back transaction can never
        // trigger an untracked B2C disbursement.
        return $this->dispatchB2C($row, $refund, $phone, $amountCents);
    }

    /**
     * Reserve the durable payout record atomically. The idempotency re-check
     * is locked inside the transaction so concurrent requests collapse to a
     * single queued row.
     *
     * @return array{0: MpesaB2cRequest, 1: bool}
     */
    private function reservePayoutRow(DepositRefundRequest $refund, string $phone, int $amountCents): array
    {
        return DB::transaction(function () use ($refund, $phone, $amountCents) {
            $existing = MpesaB2cRequest::query()
                ->withoutGlobalScopes()
                ->where('source_type', DepositRefundRequest::class)
                ->where('source_id', $refund->id)
                ->whereIn('status', MpesaB2cRequest::OPEN_STATUSES)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return [$existing, false];
            }

            $row = MpesaB2cRequest::create([
                'landlord_id' => $refund->landlord_id,
                'source_type' => DepositRefundRequest::class,
                'source_id' => $refund->id,
                'phone' => $phone,
                'amount_cents' => $amountCents,
                'reference' => 'DRR-'.$refund->id,
                'remarks' => 'Deposit refund DRR-'.$refund->id,
                'status' => MpesaB2cRequest::STATUS_QUEUED,
                'originator_conversation_id' => (string) Str::uuid(),
            ]);

            Cache::add('mpesa-b2c-refund-'.$refund->id, $row->id, now()->addHours(6));

            return [$row, true];
        });
    }

    private function dispatchB2C(
        MpesaB2cRequest $row,
        DepositRefundRequest $refund,
        string $phone,
        int $amountCents,
    ): MpesaB2cRequest {
        $config = PaymentConfiguration::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $refund->landlord_id)
            ->first();
        if ($config !== null && $config->hasMpesaApiConfig()) {
            $this->mpesa->withConfig($config);
        }

        $response = $this->mpesa->initiateB2C(
            phone: $phone,
            amount: (float) ($amountCents / 100),
            reference: $row->reference,
            remarks: $row->remarks,
        );

        if ($response === null) {
            $row->update([
                'status' => MpesaB2cRequest::STATUS_FAILED,
                'failure_reason' => 'initiateB2C returned null (gateway unreachable or rejected)',
            ]);

            return $row->refresh();
        }

        $row->update([
            'status' => MpesaB2cRequest::STATUS_SENT,
            'sent_at' => now(),
            'conversation_id' => $response['ConversationID'] ?? null,
            'last_response' => $response,
        ]);

        return $row->refresh();
    }
}
