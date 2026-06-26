<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\DepositTransaction;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phase-81 DEPOSIT-SETTLEMENT: settle a tenant's security deposit when a move-out
 * completes. Journals each move-out deduction + the arrears offset to the
 * deposit ledger (DepositTransaction), computes the refund, writes the terminal
 * refund/forfeit row, and flips lease.deposit_status. Idempotent: a lease whose
 * deposit is no longer 'held' is skipped (no double-settle). Designed to run
 * inside the move-out completion transaction (uses DB::transaction → savepoint
 * when nested).
 */
class DepositSettlementService
{
    /**
     * Phase-81 DEPOSIT-SETTLEMENT-5: journal the opening TYPE_RECEIVED entry for
     * a lease's deposit. Idempotent — skips a lease that already has one or has
     * no deposit. Called on lease creation + by the backfill command.
     *
     * @return bool true when a received row was created
     */
    public function recordReceived(Lease $lease): bool
    {
        if ((float) $lease->deposit_amount <= 0) {
            return false;
        }

        $exists = DepositTransaction::query()
            ->where('lease_id', $lease->id)
            ->where('type', DepositTransaction::TYPE_RECEIVED)
            ->exists();
        if ($exists) {
            return false;
        }

        DepositTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => (int) $lease->landlord_id,
            'processed_by' => auth()->id() ?? (int) $lease->landlord_id,
            'type' => DepositTransaction::TYPE_RECEIVED,
            'amount' => (float) $lease->deposit_amount,
            'balance_after' => (float) $lease->deposit_amount,
            'reason' => __('finance.deposit_settlement.received_reason'),
        ]);

        return true;
    }

    /**
     * @return bool true when settlement ran, false when skipped (already settled)
     */
    public function settle(MoveOut $moveOut, User $actor): bool
    {
        $lease = $moveOut->lease;
        if ($lease === null || $lease->deposit_status !== 'held') {
            return false;
        }

        return DB::transaction(function () use ($moveOut, $lease, $actor) {
            $moveOut->calculateRefund();

            $arrears = (float) $moveOut->arrears_balance;
            $withheld = (float) $moveOut->total_deductions + $arrears;
            $refund = max(0.0, (float) $moveOut->refund_amount);

            $ctx = [
                'moveOut' => $moveOut,
                'lease' => $lease,
                'actor' => $actor,
                'landlordId' => (int) $moveOut->landlord_id,
            ];

            $running = $this->journalDeductions($ctx, (float) $moveOut->deposit_held);
            $running = $this->journalArrearsOffset($ctx, $running, $arrears);
            $this->journalTerminalDisposition($ctx, $refund, $withheld);
            $this->finaliseLeaseDepositStatus($ctx, $refund, $withheld);

            return true;
        });
    }

    /**
     * Journal each itemised move-out deduction to the deposit ledger.
     * Returns the running balance after all deductions.
     *
     * @param  array{moveOut: MoveOut, lease: Lease, actor: User, landlordId: int}  $ctx
     */
    private function journalDeductions(array $ctx, float $running): float
    {
        /** @var MoveOut $moveOut */
        $moveOut = $ctx['moveOut'];
        /** @var Lease $lease */
        $lease = $ctx['lease'];

        foreach ($moveOut->deductions as $deduction) {
            $running = max(0.0, $running - (float) $deduction->amount);
            DepositTransaction::create([
                'lease_id' => $lease->id,
                'landlord_id' => $ctx['landlordId'],
                'processed_by' => $ctx['actor']->id,
                'type' => DepositTransaction::TYPE_DEDUCTION,
                'amount' => (float) $deduction->amount,
                'balance_after' => $running,
                'reason' => $deduction->description,
                'notes' => $deduction->notes,
                'move_out_id' => $moveOut->id,
            ]);
        }

        return $running;
    }

    /**
     * Offset outstanding arrears against the deposit balance.
     * Returns the running balance after the offset (unchanged if no arrears).
     *
     * @param  array{moveOut: MoveOut, lease: Lease, actor: User, landlordId: int}  $ctx
     */
    private function journalArrearsOffset(array $ctx, float $running, float $arrears): float
    {
        if ($arrears <= 0) {
            return $running;
        }

        $running = max(0.0, $running - $arrears);
        DepositTransaction::create([
            'lease_id' => $ctx['lease']->id,
            'landlord_id' => $ctx['landlordId'],
            'processed_by' => $ctx['actor']->id,
            'type' => DepositTransaction::TYPE_DEDUCTION,
            'amount' => $arrears,
            'balance_after' => $running,
            'reason' => __('finance.deposit_settlement.arrears_offset'),
            'move_out_id' => $ctx['moveOut']->id,
        ]);

        return $running;
    }

    /**
     * Write the terminal refund or partial-refund row when a refund is due.
     *
     * @param  array{moveOut: MoveOut, lease: Lease, actor: User, landlordId: int}  $ctx
     */
    private function journalTerminalDisposition(array $ctx, float $refund, float $withheld): void
    {
        if ($refund <= 0) {
            return;
        }

        /** @var MoveOut $moveOut */
        $moveOut = $ctx['moveOut'];

        DepositTransaction::create([
            'lease_id' => $ctx['lease']->id,
            'landlord_id' => $ctx['landlordId'],
            'processed_by' => $ctx['actor']->id,
            'type' => $withheld > 0 ? DepositTransaction::TYPE_PARTIAL_REFUND : DepositTransaction::TYPE_FULL_REFUND,
            'amount' => $refund,
            'balance_after' => 0,
            'reason' => __('finance.deposit_settlement.refund_reason'),
            'payment_method' => $moveOut->settlement_method,
            'reference' => $moveOut->settlement_reference,
            'move_out_id' => $moveOut->id,
        ]);
    }

    /**
     * Flip the lease deposit_status and log the change.
     *
     * @param  array{moveOut: MoveOut, lease: Lease, actor: User, landlordId: int}  $ctx
     */
    private function finaliseLeaseDepositStatus(array $ctx, float $refund, float $withheld): void
    {
        $status = $withheld <= 0 ? 'refunded' : ($refund > 0 ? 'partial_refund' : 'forfeited');
        $lease = $ctx['lease'];
        $previous = $lease->deposit_status;
        $reason = __('finance.deposit_settlement.move_out_reason', ['id' => $ctx['moveOut']->id]);

        $lease->update([
            'deposit_status' => $status,
            'deposit_refund_amount' => $refund,
            'deposit_deductions' => $withheld,
            'deposit_deduction_reason' => $reason,
            'deposit_processed_at' => now(),
            'deposit_processed_by' => $ctx['actor']->id,
        ]);

        $lease->logStatusChange("deposit:{$previous}", "deposit:{$status}", $reason);
    }
}
