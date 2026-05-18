<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Events\LeaseTerminationInitiated;
use App\Models\Lease;
use App\Models\LeaseTermination;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase-61 TERMINATION-2: state machine for lease early termination.
 *
 *   pending → acknowledged → completed
 *           ↘ disputed
 *           ↘ withdrawn
 *
 * ::initiate enforces NoticePeriodValidator; ::complete is what
 * actually flips Lease.is_active=false so the lease keeps invoicing
 * until both parties confirm.
 */
class LeaseTerminationService
{
    public function __construct(
        private readonly NoticePeriodValidator $notice,
    ) {}

    public function initiate(
        Lease $lease,
        User $initiator,
        array $payload,
    ): LeaseTermination {
        $effective = CarbonImmutable::parse($payload['termination_date']);
        $this->notice->validate('termination', $effective);

        return DB::transaction(function () use ($lease, $initiator, $effective, $payload) {
            $termination = LeaseTermination::create([
                'lease_id' => $lease->id,
                'landlord_id' => $lease->landlord_id,
                'initiated_by' => $initiator->id,
                'termination_reason' => $payload['termination_reason'],
                'termination_date' => $effective->toDateString(),
                'notice_given_at' => now(),
                'status' => LeaseTermination::STATUS_PENDING,
                'reason_text' => $payload['reason_text'] ?? null,
            ]);

            LeaseTerminationInitiated::dispatch($termination);

            return $termination;
        });
    }

    public function acknowledge(LeaseTermination $termination): LeaseTermination
    {
        return DB::transaction(function () use ($termination) {
            $termination->status = LeaseTermination::STATUS_ACKNOWLEDGED;
            $termination->acknowledged_at = now();
            $termination->save();

            return $termination;
        });
    }

    public function dispute(LeaseTermination $termination): LeaseTermination
    {
        return DB::transaction(function () use ($termination) {
            $termination->status = LeaseTermination::STATUS_DISPUTED;
            $termination->save();

            return $termination;
        });
    }

    public function withdraw(LeaseTermination $termination): LeaseTermination
    {
        return DB::transaction(function () use ($termination) {
            $termination->status = LeaseTermination::STATUS_WITHDRAWN;
            $termination->save();

            return $termination;
        });
    }

    public function complete(LeaseTermination $termination): LeaseTermination
    {
        return DB::transaction(function () use ($termination) {
            $termination->status = LeaseTermination::STATUS_COMPLETED;
            $termination->save();

            $lease = $termination->lease;
            $lease->is_active = false;
            $lease->end_date = $termination->termination_date;
            $lease->save();

            return $termination;
        });
    }
}
