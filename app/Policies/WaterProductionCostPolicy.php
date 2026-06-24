<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WaterProductionCost;

/**
 * Phase-91: production-cost entries are landlord-owned business data — caretakers
 * never see or touch the water intelligence surface. Lifecycle is landlord-only.
 */
class WaterProductionCostPolicy
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
        return $user->isScopeOwner();
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function delete(User $user, WaterProductionCost $cost): bool
    {
        return $user->isScopeOwner() && $cost->landlord_id === $user->id;
    }
}
