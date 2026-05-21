<?php

declare(strict_types=1);

namespace App\Services\Caretaker;

use App\Models\Building;
use App\Models\CaretakerAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phase-48 CARETAKER-ASSIGNMENT-UX-2: canonical write path for the
 * caretaker_assignments audit table.
 *
 * recordAssignment: idempotent (firstOrCreate). Mints a pending row +
 * also writes buildings.caretaker_id so the existing TicketObserver
 * auto-assign keeps working.
 *
 * accept: flips status to accepted + decided_at = now. Leaves
 * buildings.caretaker_id intact.
 *
 * decline: flips status to declined + decided_at = now + clears
 * buildings.caretaker_id so the landlord knows to re-assign.
 */
class CaretakerAssignmentService
{
    public function recordAssignment(User $caretaker, Building $building): CaretakerAssignment
    {
        return DB::transaction(function () use ($caretaker, $building) {
            $assignment = CaretakerAssignment::firstOrCreate(
                [
                    'caretaker_id' => $caretaker->id,
                    'building_id' => $building->id,
                ],
                [
                    'status' => CaretakerAssignment::STATUS_PENDING,
                    'assigned_at' => now(),
                ],
            );

            if ($building->caretaker_id !== $caretaker->id) {
                $building->update(['caretaker_id' => $caretaker->id]);
            }

            return $assignment;
        });
    }

    public function accept(CaretakerAssignment $assignment): CaretakerAssignment
    {
        $assignment->update([
            'status' => CaretakerAssignment::STATUS_ACCEPTED,
            'decided_at' => now(),
        ]);

        return $assignment->fresh();
    }

    /**
     * Remove a caretaker from a landlord's account entirely: detach them
     * from every building the landlord owns, mark their open assignments
     * declined for the audit trail, and sever the account link so they
     * drop off the team list and lose access to the landlord's properties.
     */
    public function removeFromLandlord(User $caretaker, int $landlordId): void
    {
        DB::transaction(function () use ($caretaker, $landlordId) {
            $buildingIds = Building::where('landlord_id', $landlordId)
                ->where('caretaker_id', $caretaker->id)
                ->pluck('id');

            Building::whereIn('id', $buildingIds)->update(['caretaker_id' => null]);

            CaretakerAssignment::where('caretaker_id', $caretaker->id)
                ->whereIn('building_id', $buildingIds)
                ->whereIn('status', [CaretakerAssignment::STATUS_PENDING, CaretakerAssignment::STATUS_ACCEPTED])
                ->update([
                    'status' => CaretakerAssignment::STATUS_DECLINED,
                    'decided_at' => now(),
                    'decision_reason' => 'removed_by_landlord',
                ]);

            // landlord_id is guarded against mass assignment on User, so
            // forceFill the sever rather than update([...]) (which no-ops).
            $caretaker->forceFill(['landlord_id' => null])->save();
        });
    }

    public function decline(CaretakerAssignment $assignment, ?string $reason = null): CaretakerAssignment
    {
        return DB::transaction(function () use ($assignment, $reason) {
            $assignment->update([
                'status' => CaretakerAssignment::STATUS_DECLINED,
                'decided_at' => now(),
                'decision_reason' => $reason,
            ]);

            Building::where('id', $assignment->building_id)
                ->where('caretaker_id', $assignment->caretaker_id)
                ->update(['caretaker_id' => null]);

            return $assignment->fresh();
        });
    }
}
