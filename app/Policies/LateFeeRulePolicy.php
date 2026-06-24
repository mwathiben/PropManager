<?php

namespace App\Policies;

use App\Models\LateFeePolicy;
use App\Models\User;

class LateFeeRulePolicy
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

    public function view(User $user, LateFeePolicy $lateFeePolicy): bool
    {
        return $this->ownsPolicy($user, $lateFeePolicy);
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function update(User $user, LateFeePolicy $lateFeePolicy): bool
    {
        return $user->isScopeOwner() && $lateFeePolicy->landlord_id === $user->id;
    }

    public function delete(User $user, LateFeePolicy $lateFeePolicy): bool
    {
        return $user->isScopeOwner() && $lateFeePolicy->landlord_id === $user->id;
    }

    private function ownsPolicy(User $user, LateFeePolicy $lateFeePolicy): bool
    {
        if ($user->isScopeOwner()) {
            return $lateFeePolicy->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $lateFeePolicy->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
