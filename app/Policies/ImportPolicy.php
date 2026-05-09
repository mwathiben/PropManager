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
        return $user->isLandlord();
    }

    public function view(User $user, Import $import): bool
    {
        return $user->isLandlord() && $import->landlord_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function delete(User $user, Import $import): bool
    {
        return $user->isLandlord() && $import->landlord_id === $user->id;
    }
}
