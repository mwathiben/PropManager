<?php

namespace App\Policies;

use App\Models\LandlordPayoutAccount;
use App\Models\User;

class LandlordPayoutAccountPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function view(User $user, LandlordPayoutAccount $account): bool
    {
        return $user->isScopeOwner() && $account->landlord_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function update(User $user, LandlordPayoutAccount $account): bool
    {
        return $user->isScopeOwner() && $account->landlord_id === $user->id;
    }

    public function setPrimary(User $user, LandlordPayoutAccount $account): bool
    {
        return $user->isScopeOwner() && $account->landlord_id === $user->id;
    }

    public function sync(User $user, LandlordPayoutAccount $account): bool
    {
        return $user->isScopeOwner() && $account->landlord_id === $user->id;
    }

    public function delete(User $user, LandlordPayoutAccount $account): bool
    {
        return $user->isScopeOwner() && $account->landlord_id === $user->id;
    }
}
