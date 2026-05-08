<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;

class BuildingPolicy
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
     * Determine whether the user can view any buildings.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the building.
     */
    public function view(User $user, Building $building): bool
    {
        return $this->hasAccess($user, $building);
    }

    /**
     * Determine whether the user can create buildings.
     */
    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    /**
     * Determine whether the user can update the building.
     */
    public function update(User $user, Building $building): bool
    {
        if ($user->isLandlord()) {
            return $building->landlord_id === $user->id;
        }

        // Caretakers can update buildings they are assigned to
        if ($user->isCaretaker()) {
            return $building->caretaker_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the building.
     */
    public function delete(User $user, Building $building): bool
    {
        if ($building->isWing()) {
            return false;
        }

        return $user->isLandlord() && $building->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can manage units in this building.
     */
    public function manageUnits(User $user, Building $building): bool
    {
        if ($user->isLandlord()) {
            return $building->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $building->landlord_id === $user->landlord_id;
        }

        return false;
    }

    /**
     * Determine whether the user can manage water settings.
     */
    public function manageWaterSettings(User $user, Building $building): bool
    {
        return $user->isLandlord() && $building->landlord_id === $user->id;
    }

    /**
     * Check if user has access to the building.
     */
    protected function hasAccess(User $user, Building $building): bool
    {
        if ($user->isLandlord()) {
            return $building->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $building->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            // Tenant can view building where they have an active lease
            return $building->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
