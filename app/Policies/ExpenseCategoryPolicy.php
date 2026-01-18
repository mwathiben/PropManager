<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
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
        return $user->isLandlord() || $user->isCaretaker();
    }

    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->ownsCategory($user, $expenseCategory);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->isLandlord() && $expenseCategory->landlord_id === $user->id;
    }

    public function delete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->isLandlord() && $expenseCategory->landlord_id === $user->id;
    }

    private function ownsCategory(User $user, ExpenseCategory $expenseCategory): bool
    {
        if ($user->isLandlord()) {
            return $expenseCategory->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $expenseCategory->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
