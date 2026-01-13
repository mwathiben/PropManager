<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaterReading;

class WaterReadingPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any water readings.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the water reading.
     */
    public function view(User $user, WaterReading $waterReading): bool
    {
        if ($user->isLandlord()) {
            return $waterReading->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $waterReading->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            // Tenant can view readings for their rented unit
            $activeLease = $user->lease;

            return $activeLease && $activeLease->unit_id === $waterReading->unit_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create water readings.
     */
    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    /**
     * Determine whether the user can update the water reading.
     */
    public function update(User $user, WaterReading $waterReading): bool
    {
        // Cannot update invoiced readings
        if ($waterReading->is_invoiced) {
            return false;
        }

        return $this->canManage($user, $waterReading);
    }

    /**
     * Determine whether the user can delete the water reading.
     */
    public function delete(User $user, WaterReading $waterReading): bool
    {
        // Cannot delete invoiced readings
        if ($waterReading->is_invoiced) {
            return false;
        }

        return $this->canManage($user, $waterReading);
    }

    /**
     * Determine whether the user can approve the water reading.
     */
    public function approve(User $user, WaterReading $waterReading): bool
    {
        // Only landlords can approve readings submitted by caretakers
        return $user->isLandlord() && $waterReading->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can reject the water reading.
     */
    public function reject(User $user, WaterReading $waterReading): bool
    {
        return $this->approve($user, $waterReading);
    }

    /**
     * Check if user can manage the water reading.
     */
    protected function canManage(User $user, WaterReading $waterReading): bool
    {
        if ($user->isLandlord()) {
            return $waterReading->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $waterReading->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
