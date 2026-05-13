<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
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
     * Determine whether the user can view any leases.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the lease.
     */
    public function view(User $user, Lease $lease): bool
    {
        if ($user->isLandlord()) {
            return $lease->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $lease->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            return $lease->tenant_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create leases.
     */
    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    /**
     * Determine whether the user can update the lease.
     */
    public function update(User $user, Lease $lease): bool
    {
        return $this->canManage($user, $lease);
    }

    /**
     * Determine whether the user can delete the lease.
     */
    public function delete(User $user, Lease $lease): bool
    {
        return $user->isLandlord() && $lease->landlord_id === $user->id;
    }

    /**
     * Phase-19 POLICY-1: super-admin only via before(); explicit deny here
     * so the destructive force-delete path is gated.
     */
    public function forceDelete(User $user, Lease $lease): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-1: restoring mirrors delete() — landlord owner can
     * undo a soft-delete.
     */
    public function restore(User $user, Lease $lease): bool
    {
        return $user->isLandlord() && $lease->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can adjust rent.
     */
    public function adjustRent(User $user, Lease $lease): bool
    {
        return $user->isLandlord() && $lease->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can terminate the lease.
     */
    public function terminate(User $user, Lease $lease): bool
    {
        return $this->canManage($user, $lease);
    }

    /**
     * Determine whether the user can start move-out process.
     */
    public function moveOut(User $user, Lease $lease): bool
    {
        return $this->canManage($user, $lease) && $lease->is_active;
    }

    /**
     * Check if user can manage the lease.
     */
    protected function canManage(User $user, Lease $lease): bool
    {
        if ($user->isLandlord()) {
            return $lease->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $lease->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
