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
}
