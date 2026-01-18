<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
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

    public function view(User $user, Vendor $vendor): bool
    {
        return $this->ownsVendor($user, $vendor);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $this->ownsVendor($user, $vendor);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->isLandlord() && $vendor->landlord_id === $user->id;
    }

    private function ownsVendor(User $user, Vendor $vendor): bool
    {
        if ($user->isLandlord()) {
            return $vendor->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $vendor->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
