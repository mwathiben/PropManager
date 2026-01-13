<?php

namespace App\Policies;

use App\Models\InvoiceTemplate;
use App\Models\User;

class InvoiceTemplatePolicy
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

    public function view(User $user, InvoiceTemplate $template): bool
    {
        return $this->canManage($user, $template);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, InvoiceTemplate $template): bool
    {
        return $user->isLandlord() && $template->landlord_id === $user->id;
    }

    public function delete(User $user, InvoiceTemplate $template): bool
    {
        return $user->isLandlord() && $template->landlord_id === $user->id;
    }

    protected function canManage(User $user, InvoiceTemplate $template): bool
    {
        if ($user->isLandlord()) {
            return $template->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $template->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
