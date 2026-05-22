<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Enums\MeterStatus;
use App\Models\Meter;
use App\Models\WaterReading;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase-86 METER-LIFECYCLE: replacing a meter is an explicit event, not an
 * inferred reading. A swapped meter usually starts at a non-zero value, so a
 * naive reading on the new meter would misfire the below-previous guard or
 * compute a huge/negative consumption. This records the old meter's closing
 * read, retires it, and stands up a successor measured from its OWN baseline —
 * preserving consumption continuity across the swap.
 */
class MeterReplacementService
{
    /**
     * @param  float  $oldFinalReading  the final value read off the meter being removed
     * @param  float  $newInitialReading  the value the replacement meter starts at (often non-zero)
     */
    public function replace(
        Meter $old,
        float $oldFinalReading,
        string $newSerial,
        float $newInitialReading,
        ?string $readingDate = null
    ): Meter {
        if (! $old->isActive()) {
            throw new InvalidArgumentException('Only an active meter can be replaced.');
        }

        $closingBaseline = $old->baselineForNextReading();
        if ($oldFinalReading < $closingBaseline) {
            throw new InvalidArgumentException(
                "Final reading ({$oldFinalReading}) cannot be below the meter's last reading ({$closingBaseline})."
            );
        }

        return DB::transaction(function () use ($old, $oldFinalReading, $newSerial, $newInitialReading, $readingDate, $closingBaseline) {
            // Closing read on the outgoing meter — billable like any reading,
            // so it enters the normal review flow (status pending).
            WaterReading::create([
                'unit_id' => $old->unit_id,
                'meter_id' => $old->id,
                'landlord_id' => $old->landlord_id,
                'previous_reading' => $closingBaseline,
                'current_reading' => $oldFinalReading,
                'reading_date' => $readingDate ?? now()->toDateString(),
                'status' => 'pending',
            ]);

            $new = Meter::create([
                'landlord_id' => $old->landlord_id,
                'building_id' => $old->building_id,
                'unit_id' => $old->unit_id,
                'parent_meter_id' => $old->parent_meter_id,
                'serial_number' => $newSerial,
                'utility_type' => $old->utility_type,
                'meter_type' => $old->meter_type,
                'status' => MeterStatus::Active->value,
                'initial_reading' => $newInitialReading,
                'installed_at' => now()->toDateString(),
            ]);

            $old->update([
                'status' => MeterStatus::Replaced->value,
                'decommissioned_at' => now()->toDateString(),
                'replaced_by_meter_id' => $new->id,
            ]);

            return $new;
        });
    }

    public function decommission(Meter $meter): void
    {
        $meter->update([
            'status' => MeterStatus::Decommissioned->value,
            'decommissioned_at' => now()->toDateString(),
        ]);
    }
}
