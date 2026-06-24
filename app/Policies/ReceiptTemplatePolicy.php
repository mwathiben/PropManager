<?php

namespace App\Policies;

use App\Models\ReceiptTemplate;
use App\Models\User;

class ReceiptTemplatePolicy
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

    public function view(User $user, ReceiptTemplate $template): bool
    {
        return $this->canManage($user, $template);
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function update(User $user, ReceiptTemplate $template): bool
    {
        return $user->isScopeOwner() && $template->landlord_id === $user->id;
    }

    public function delete(User $user, ReceiptTemplate $template): bool
    {
        return $user->isScopeOwner() && $template->landlord_id === $user->id;
    }

    protected function canManage(User $user, ReceiptTemplate $template): bool
    {
        if ($user->isScopeOwner()) {
            return $template->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $template->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
