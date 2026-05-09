<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
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

    public function view(User $user, Refund $refund): bool
    {
        if ($user->isLandlord()) {
            return $refund->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $refund->landlord_id === $user->landlord_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    public function update(User $user, Refund $refund): bool
    {
        return $this->isOwner($user, $refund);
    }

    public function approve(User $user, Refund $refund): bool
    {
        return $this->isOwner($user, $refund);
    }

    public function process(User $user, Refund $refund): bool
    {
        return $this->isOwner($user, $refund);
    }

    public function cancel(User $user, Refund $refund): bool
    {
        return $this->isOwner($user, $refund);
    }

    public function delete(User $user, Refund $refund): bool
    {
        return $this->isOwner($user, $refund);
    }

    private function isOwner(User $user, Refund $refund): bool
    {
        if ($user->isLandlord()) {
            return $refund->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $refund->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
