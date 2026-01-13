<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any properties.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    /**
     * Determine whether the user can view the property.
     */
    public function view(User $user, Property $property): bool
    {
        return $this->ownsOrManages($user, $property);
    }

    /**
     * Determine whether the user can create properties.
     */
    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    /**
     * Determine whether the user can update the property.
     */
    public function update(User $user, Property $property): bool
    {
        return $user->isLandlord() && $property->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can delete the property.
     */
    public function delete(User $user, Property $property): bool
    {
        return $user->isLandlord() && $property->landlord_id === $user->id;
    }

    /**
     * Check if user owns or manages (caretaker) the property.
     */
    protected function ownsOrManages(User $user, Property $property): bool
    {
        if ($user->isLandlord()) {
            return $property->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $property->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
