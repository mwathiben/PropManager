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
            $moveOut->calculateRefund(); // refresh total_deductions + refund_amount

            $held = (float) $moveOut->deposit_held;
            $arrears = (float) $moveOut->arrears_balance;
            $deductions = (float) $moveOut->total_deductions;
            $withheld = $deductions + $arrears;
            $refund = max(0.0, (float) $moveOut->refund_amount);
            $landlordId = (int) $moveOut->landlord_id;

            $running = $held;

            // Itemise each move-out deduction into the deposit ledger.
            foreach ($moveOut->deductions as $deduction) {
                $running = max(0.0, $running - (float) $deduction->amount);
                DepositTransaction::create([
                    'lease_id' => $lease->id,
                    'landlord_id' => $landlordId,
                    'processed_by' => $actor->id,
                    'type' => DepositTransaction::TYPE_DEDUCTION,
                    'amount' => (float) $deduction->amount,
                    'balance_after' => $running,
                    'reason' => $deduction->description,
                    'notes' => $deduction->notes,
                    'move_out_id' => $moveOut->id,
                ]);
            }

            // Offset outstanding arrears against the deposit.
            if ($arrears > 0) {
                $running = max(0.0, $running - $arrears);
                DepositTransaction::create([
                    'lease_id' => $lease->id,
                    'landlord_id' => $landlordId,
                    'processed_by' => $actor->id,
                    'type' => DepositTransaction::TYPE_DEDUCTION,
                    'amount' => $arrears,
                    'balance_after' => $running,
                    'reason' => __('finance.deposit_settlement.arrears_offset'),
                    'move_out_id' => $moveOut->id,
                ]);
            }

            // Terminal disposition of the remaining balance.
            if ($refund > 0) {
                DepositTransaction::create([
                    'lease_id' => $lease->id,
                    'landlord_id' => $landlordId,
                    'processed_by' => $actor->id,
                    'type' => $withheld > 0 ? DepositTransaction::TYPE_PARTIAL_REFUND : DepositTransaction::TYPE_FULL_REFUND,
                    'amount' => $refund,
                    'balance_after' => 0,
                    'reason' => __('finance.deposit_settlement.refund_reason'),
                    'payment_method' => $moveOut->settlement_method,
                    'reference' => $moveOut->settlement_reference,
                    'move_out_id' => $moveOut->id,
                ]);
            }

            $status = $withheld <= 0 ? 'refunded' : ($refund > 0 ? 'partial_refund' : 'forfeited');
            $previous = $lease->deposit_status;

            $lease->update([
                'deposit_status' => $status,
                'deposit_refund_amount' => $refund,
                'deposit_deductions' => $withheld,
                'deposit_deduction_reason' => __('finance.deposit_settlement.move_out_reason', ['id' => $moveOut->id]),
                'deposit_processed_at' => now(),
                'deposit_processed_by' => $actor->id,
            ]);

            $lease->logStatusChange("deposit:{$previous}", "deposit:{$status}", __('finance.deposit_settlement.move_out_reason', ['id' => $moveOut->id]));

            return true;
        });
    }
}
