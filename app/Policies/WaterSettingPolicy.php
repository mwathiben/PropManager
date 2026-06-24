<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaterSetting;

class WaterSettingPolicy
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

    public function view(User $user, WaterSetting $waterSetting): bool
    {
        return $this->ownsSetting($user, $waterSetting);
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function update(User $user, WaterSetting $waterSetting): bool
    {
        return $user->isScopeOwner() && $waterSetting->landlord_id === $user->id;
    }

    public function delete(User $user, WaterSetting $waterSetting): bool
    {
        return $user->isScopeOwner() && $waterSetting->landlord_id === $user->id;
    }

    private function ownsSetting(User $user, WaterSetting $waterSetting): bool
    {
        if ($user->isScopeOwner()) {
            return $waterSetting->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $waterSetting->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
