<?php

namespace App\Policies;

use App\Models\MoveOutDeductionCategory;
use App\Models\User;

class MoveOutDeductionCategoryPolicy
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

    public function view(User $user, MoveOutDeductionCategory $category): bool
    {
        if ($category->isGlobal()) {
            return $user->isScopeOwner() || $user->isCaretaker();
        }

        return $this->ownsCategory($user, $category);
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function update(User $user, MoveOutDeductionCategory $category): bool
    {
        if ($category->isGlobal()) {
            return false;
        }

        return $user->isScopeOwner() && $category->landlord_id === $user->id;
    }

    public function delete(User $user, MoveOutDeductionCategory $category): bool
    {
        if ($category->isGlobal()) {
            return false;
        }

        return $user->isScopeOwner() && $category->landlord_id === $user->id;
    }

    private function ownsCategory(User $user, MoveOutDeductionCategory $category): bool
    {
        if ($user->isScopeOwner()) {
            return $category->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $category->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
