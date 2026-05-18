<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Events\LeaseTransferRequested;
use App\Models\Lease;
use App\Models\LeaseTransfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase-61 TRANSFER-2: state machine for tenant assignment / sublet.
 *
 *   requested → landlord_approved → completed
 *             ↘ rejected
 *             ↘ withdrawn
 *
 * ::complete is what actually swaps Lease.tenant_id; until then the
 * lease keeps invoicing the outgoing tenant.
 */
class LeaseTransferService
{
    public function __construct(
        private readonly NoticePeriodValidator $notice,
    ) {}

    public function request(
        Lease $lease,
        User $outgoing,
        User $incoming,
        array $payload,
    ): LeaseTransfer {
        $effective = CarbonImmutable::parse($payload['transfer_date']);
        $this->notice->validate('transfer', $effective);

        return DB::transaction(function () use ($lease, $outgoing, $incoming, $effective, $payload) {
            $transfer = LeaseTransfer::create([
                'lease_id' => $lease->id,
                'landlord_id' => $lease->landlord_id,
                'outgoing_tenant_id' => $outgoing->id,
                'incoming_tenant_id' => $incoming->id,
                'initiated_by' => $outgoing->id,
                'transfer_date' => $effective->toDateString(),
                'status' => LeaseTransfer::STATUS_REQUESTED,
                'transfer_fee_amount' => $payload['transfer_fee_amount'] ?? null,
                'reason_text' => $payload['reason_text'] ?? null,
            ]);

            LeaseTransferRequested::dispatch($transfer);

            return $transfer;
        });
    }

    public function approve(LeaseTransfer $transfer): LeaseTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer->status = LeaseTransfer::STATUS_LANDLORD_APPROVED;
            $transfer->landlord_approved_at = now();
            $transfer->save();

            return $transfer;
        });
    }

    public function reject(LeaseTransfer $transfer): LeaseTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer->status = LeaseTransfer::STATUS_REJECTED;
            $transfer->save();

            return $transfer;
        });
    }

    public function withdraw(LeaseTransfer $transfer): LeaseTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer->status = LeaseTransfer::STATUS_WITHDRAWN;
            $transfer->save();

            return $transfer;
        });
    }

    public function complete(LeaseTransfer $transfer): LeaseTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer->status = LeaseTransfer::STATUS_COMPLETED;
            $transfer->save();

            $lease = $transfer->lease;
            $lease->tenant_id = $transfer->incoming_tenant_id;
            $lease->save();

            return $transfer;
        });
    }
}
