<?php

namespace App\Policies;

use App\Models\PropertyOwner;
use App\Models\User;

/**
 * Phase-101 OWNER-FOUNDATION: owners are managed by the landlord (and viewed by their
 * caretaker); only the owning landlord may mutate them.
 */
class PropertyOwnerPolicy
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
        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function view(User $user, PropertyOwner $owner): bool
    {
        return $this->owns($user, $owner);
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function update(User $user, PropertyOwner $owner): bool
    {
        return $user->isScopeOwner() && $owner->landlord_id === $user->id;
    }

    public function delete(User $user, PropertyOwner $owner): bool
    {
        return $user->isScopeOwner() && $owner->landlord_id === $user->id;
    }

    private function owns(User $user, PropertyOwner $owner): bool
    {
        if ($user->isScopeOwner()) {
            return $owner->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $owner->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
