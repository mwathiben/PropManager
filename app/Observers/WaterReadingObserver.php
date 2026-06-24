<?php

namespace App\Observers;

use App\Exceptions\WaterReading\ReadingLockedException;
use App\Models\WaterReading;
use App\Services\Water\WaterTariffService;
use Illuminate\Support\Facades\Auth;

class WaterReadingObserver
{
    public function __construct(
        private WaterTariffService $tariffService
    ) {}

    /**
     * Handle the WaterReading "creating" event.
     * Auto-calculate consumption, cost, set landlord_id, and track who recorded it.
     */
    public function creating(WaterReading $waterReading): void
    {
        // 1. Set landlord_id based on user role
        if (Auth::check()) {
            $user = Auth::user();
            $waterReading->landlord_id = $user->isScopeOwner()
                ? $user->id
                : $user->landlord_id;

            // Track who recorded this reading
            if (! $waterReading->recorded_by) {
                $waterReading->recorded_by = $user->id;
            }
        }

        // 2. Calculate consumption (current - previous)
        $waterReading->consumption = max(0, $waterReading->current_reading - $waterReading->previous_reading);

        // 3. Phase-87: cost via the tariff engine (tiered/flat). Non-destructive:
        // with no bands configured this equals consumption * flat rate.
        $waterReading->cost = $this->tariffService->costForReading($waterReading);

        // 4. Set status to pending by default (migration handles this, but being explicit)
        if (! $waterReading->status) {
            $waterReading->status = 'pending';
        }
    }

    /**
     * Handle the WaterReading "updating" event.
     * Recalculate if readings change and enforce approval workflow rules.
     */
    public function updating(WaterReading $waterReading): void
    {
        // Prevent updates to invoiced readings (regardless of approval status)
        if ($waterReading->is_invoiced && $waterReading->isDirty(['current_reading', 'previous_reading', 'photo_path'])) {
            throw new ReadingLockedException($waterReading->id, ReadingLockedException::INVOICED);
        }

        // Prevent modifications to approved readings (except for approval workflow fields)
        if ($waterReading->getOriginal('status') === 'approved' &&
            $waterReading->isDirty(['current_reading', 'previous_reading', 'photo_path']) &&
            ! $waterReading->isDirty(['status', 'reviewed_by', 'reviewed_at', 'review_notes'])) {
            throw new ReadingLockedException($waterReading->id, ReadingLockedException::APPROVED);
        }

        // Recalculate if readings changed
        if ($waterReading->isDirty(['current_reading', 'previous_reading'])) {
            $waterReading->consumption = max(0, $waterReading->current_reading - $waterReading->previous_reading);
            $waterReading->cost = $this->tariffService->costForReading($waterReading);
        }
    }
}
