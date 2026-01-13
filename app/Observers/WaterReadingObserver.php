<?php

namespace App\Observers;

use App\Models\Setting;
use App\Models\WaterReading;
use Illuminate\Support\Facades\Auth;

class WaterReadingObserver
{
    /**
     * Handle the WaterReading "creating" event.
     * Auto-calculate consumption, cost, set landlord_id, and track who recorded it.
     */
    public function creating(WaterReading $waterReading): void
    {
        // 1. Set landlord_id based on user role
        if (Auth::check()) {
            $user = Auth::user();
            $waterReading->landlord_id = $user->role === 'landlord'
                ? $user->id
                : $user->landlord_id;

            // Track who recorded this reading
            if (! $waterReading->recorded_by) {
                $waterReading->recorded_by = $user->id;
            }
        }

        // 2. Calculate consumption (current - previous)
        $waterReading->consumption = max(0, $waterReading->current_reading - $waterReading->previous_reading);

        // 3. Calculate cost
        // TODO: Make this configurable per landlord or property
        // For now, using a flat rate of 150 KES per unit (cubic meter)
        $waterReading->cost = $waterReading->consumption * $this->getWaterRate($waterReading);

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
            throw new \Exception('Cannot modify readings that have been invoiced. Please void the invoice first.');
        }

        // Prevent modifications to approved readings (except for approval workflow fields)
        if ($waterReading->getOriginal('status') === 'approved' &&
            $waterReading->isDirty(['current_reading', 'previous_reading', 'photo_path']) &&
            ! $waterReading->isDirty(['status', 'reviewed_by', 'reviewed_at', 'review_notes'])) {
            throw new \Exception('Cannot modify approved readings. Please reject the reading first if changes are needed.');
        }

        // Recalculate if readings changed
        if ($waterReading->isDirty(['current_reading', 'previous_reading'])) {
            $waterReading->consumption = max(0, $waterReading->current_reading - $waterReading->previous_reading);
            $waterReading->cost = $waterReading->consumption * $this->getWaterRate($waterReading);
        }
    }

    /**
     * Get the water rate for a given reading.
     * Fetches from landlord's settings or uses default of 150 KES per cubic meter.
     */
    private function getWaterRate(WaterReading $waterReading): float
    {
        // Fetch configured rate from settings, default to 150 KES
        $rate = Setting::get('water_rate_per_unit', '150.00', $waterReading->landlord_id);

        return (float) $rate;
    }
}
