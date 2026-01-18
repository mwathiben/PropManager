<?php

namespace App\Policies;

use App\Models\DepositTransaction;
use App\Models\User;

class DepositTransactionPolicy
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
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    public function view(User $user, DepositTransaction $depositTransaction): bool
    {
        if ($user->isLandlord()) {
            return $depositTransaction->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $depositTransaction->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            return $depositTransaction->lease?->tenant_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    public function update(User $user, DepositTransaction $depositTransaction): bool
    {
        return $user->isLandlord() && $depositTransaction->landlord_id === $user->id;
    }

    public function delete(User $user, DepositTransaction $depositTransaction): bool
    {
        return $user->isLandlord() && $depositTransaction->landlord_id === $user->id;
    }
}
