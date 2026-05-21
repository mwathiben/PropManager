<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Mail\RentHikeNotice;
use App\Models\Lease;
use App\Models\RentEscalation;
use App\Models\RentHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-83 RENT-ESCALATION: schedule future rent increases and apply them
 * (rent_amount + rent_histories audit + tenant notice) when their effective
 * date arrives. Idempotent: applying is gated on status = scheduled.
 */
class RentEscalationService
{
    /**
     * Schedule a future increase on a lease.
     *
     * @param  array{escalation_type:string, amount:float|string, effective_date:string, notes?:string|null}  $data
     */
    public function schedule(Lease $lease, array $data): RentEscalation
    {
        return RentEscalation::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'escalation_type' => $data['escalation_type'],
            'amount' => $data['amount'],
            'effective_date' => $data['effective_date'],
            'status' => RentEscalation::STATUS_SCHEDULED,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function cancel(RentEscalation $escalation): bool
    {
        if ($escalation->status !== RentEscalation::STATUS_SCHEDULED) {
            return false;
        }

        return $escalation->update(['status' => RentEscalation::STATUS_CANCELLED]);
    }

    /**
     * The rent a scheduled escalation would produce from the lease's current rent.
     */
    public function preview(RentEscalation $escalation): float
    {
        return $escalation->computeNewRent((float) $escalation->lease->rent_amount);
    }

    /**
     * Apply one scheduled escalation: bump rent, write the audit row, link it,
     * flip status, and queue the tenant notice. Idempotent on status.
     *
     * @return bool whether it was applied
     */
    public function apply(RentEscalation $escalation): bool
    {
        if ($escalation->status !== RentEscalation::STATUS_SCHEDULED) {
            return false;
        }

        $lease = $escalation->lease;
        if (! $lease) {
            return false;
        }

        $oldAmount = (float) $lease->rent_amount;
        $newAmount = $escalation->computeNewRent($oldAmount);

        DB::transaction(function () use ($escalation, $lease, $oldAmount, $newAmount) {
            $history = RentHistory::create([
                'lease_id' => $lease->id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'effective_date' => $escalation->effective_date->toDateString(),
                'reason' => __('lease.escalation.history_reason'),
                'notification_sent' => true,
            ]);

            $lease->update(['rent_amount' => $newAmount]);

            $escalation->update([
                'status' => RentEscalation::STATUS_APPLIED,
                'applied_at' => now(),
                'new_rent_amount' => $newAmount,
                'rent_history_id' => $history->id,
            ]);
        });

        $lease->loadMissing(['tenant', 'unit.building.property']);
        if ($lease->tenant) {
            try {
                Mail::to($lease->tenant)->queue(new RentHikeNotice(
                    $lease,
                    $oldAmount,
                    $newAmount,
                    $escalation->effective_date->toDateString(),
                    __('lease.escalation.history_reason'),
                ));
            } catch (\Throwable $e) {
                Log::warning('rent escalation notice failed to queue', [
                    'escalation_id' => $escalation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    /**
     * Phase-83 RENT-ESCALATION-3: when a lease auto-renews, fold any escalation
     * scheduled to take effect on or before the new term's start into the renewed
     * lease's rent (compounding in date order) instead of inheriting flat. Marks
     * each escalation applied + writes a RentHistory row on the renewed lease, so
     * the daily cron never re-applies them.
     */
    public function applyAtRenewal(Lease $original, Lease $renewed): void
    {
        $due = RentEscalation::query()
            ->withoutGlobalScopes()
            ->where('lease_id', $original->id)
            ->scheduled()
            ->whereDate('effective_date', '<=', $renewed->start_date->toDateString())
            ->orderBy('effective_date')
            ->get();

        foreach ($due as $escalation) {
            $oldAmount = (float) $renewed->rent_amount;
            $newAmount = $escalation->computeNewRent($oldAmount);

            $history = RentHistory::create([
                'lease_id' => $renewed->id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'effective_date' => $renewed->start_date->toDateString(),
                'reason' => __('lease.escalation.renewal_reason'),
                'notification_sent' => false,
            ]);

            $renewed->update(['rent_amount' => $newAmount]);

            $escalation->update([
                'status' => RentEscalation::STATUS_APPLIED,
                'applied_at' => now(),
                'new_rent_amount' => $newAmount,
                'rent_history_id' => $history->id,
            ]);
        }
    }

    /**
     * Apply every due (scheduled, effective_date <= today) escalation across all
     * landlords. Used by the rent:apply-escalations cron.
     *
     * @return int how many were applied
     */
    public function applyAllDue(): int
    {
        $applied = 0;

        RentEscalation::query()
            ->withoutGlobalScopes()
            ->due()
            ->with('lease')
            ->chunkById(200, function ($escalations) use (&$applied) {
                foreach ($escalations as $escalation) {
                    if ($this->apply($escalation)) {
                        $applied++;
                    }
                }
            });

        return $applied;
    }
}
