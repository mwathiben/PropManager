<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
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

    public function view(User $user, Import $import): bool
    {
        return $this->isOwner($user, $import);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    public function reprocess(User $user, Import $import): bool
    {
        return $this->isOwner($user, $import);
    }

    public function delete(User $user, Import $import): bool
    {
        return $this->isOwner($user, $import);
    }

    private function isOwner(User $user, Import $import): bool
    {
        if ($user->isLandlord()) {
            return $import->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $import->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
