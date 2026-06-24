<?php

namespace App\Policies;

use App\Models\OwnerPayout;
use App\Models\User;

/**
 * Phase-103 OWNER-PAYOUTS: payouts are recorded and managed by the owning landlord (and
 * viewable by their caretaker as PM staff). Only the owning landlord may create or void.
 */
class OwnerPayoutPolicy
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

    public function view(User $user, OwnerPayout $payout): bool
    {
        if ($user->isScopeOwner()) {
            return $payout->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $payout->landlord_id === $user->landlord_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function void(User $user, OwnerPayout $payout): bool
    {
        return $user->isScopeOwner() && $payout->landlord_id === $user->id;
    }
}
