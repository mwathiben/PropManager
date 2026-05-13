<?php

namespace App\Policies;

use App\Models\KycRequirement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KycRequirementPolicy
{
    use HandlesAuthorization;

    public function before(User $user): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isLandlord();
    }

    public function view(User $user, KycRequirement $kycRequirement): bool
    {
        if ($kycRequirement->isGlobal()) {
            return true;
        }

        return $kycRequirement->landlord_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, KycRequirement $kycRequirement): bool
    {
        if ($kycRequirement->isGlobal()) {
            return false;
        }

        return $kycRequirement->landlord_id === $user->id;
    }

    public function delete(User $user, KycRequirement $kycRequirement): bool
    {
        if ($kycRequirement->isGlobal()) {
            return false;
        }

        return $kycRequirement->landlord_id === $user->id;
    }

    /**
     * Phase-19 POLICY-1: super-admin only via before(); explicit deny here
     * so the destructive force-delete path is gated.
     */
    public function forceDelete(User $user, KycRequirement $kycRequirement): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-1: restoring mirrors delete() — only landlord-owned
     * requirements can be undone; global requirements were never
     * landlord-deletable so the restore path is symmetrically denied.
     */
    public function restore(User $user, KycRequirement $kycRequirement): bool
    {
        if ($kycRequirement->isGlobal()) {
            return false;
        }

        return $kycRequirement->landlord_id === $user->id;
    }
}
