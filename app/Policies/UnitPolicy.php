<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
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
     * Determine whether the user can view any units.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the unit.
     */
    public function view(User $user, Unit $unit): bool
    {
        return $this->hasAccess($user, $unit);
    }

    /**
     * Determine whether the user can create units.
     */
    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    /**
     * Determine whether the user can update the unit.
     */
    public function update(User $user, Unit $unit): bool
    {
        return $this->canManage($user, $unit);
    }

    /**
     * Determine whether the user can delete the unit.
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->isLandlord() && $unit->landlord_id === $user->id;
    }

    /**
     * Phase-19 POLICY-1: super-admin only via before(); explicit deny here
     * so the destructive force-delete path is gated.
     */
    public function forceDelete(User $user, Unit $unit): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-1: restoring mirrors delete() — landlord owner can
     * undo a soft-delete.
     */
    public function restore(User $user, Unit $unit): bool
    {
        return $user->isLandlord() && $unit->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can add a lease to this unit.
     */
    public function addLease(User $user, Unit $unit): bool
    {
        return $this->canManage($user, $unit) && $unit->status === 'vacant';
    }

    /**
     * Check if user has access to view the unit.
     */
    protected function hasAccess(User $user, Unit $unit): bool
    {
        if ($user->isLandlord()) {
            return $unit->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $unit->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            // Tenant can view their rented unit
            $activeLease = $user->lease;

            return $activeLease && $activeLease->unit_id === $unit->id;
        }

        return false;
    }

    /**
     * Check if user can manage (create/update) the unit.
     */
    protected function canManage(User $user, Unit $unit): bool
    {
        if ($user->isLandlord()) {
            return $unit->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $unit->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
