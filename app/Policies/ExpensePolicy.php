<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
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

    public function view(User $user, Expense $expense): bool
    {
        return $this->ownsExpense($user, $expense);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->ownsExpense($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->isLandlord() && $expense->landlord_id === $user->id;
    }

    private function ownsExpense(User $user, Expense $expense): bool
    {
        if ($user->isLandlord()) {
            return $expense->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $expense->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
