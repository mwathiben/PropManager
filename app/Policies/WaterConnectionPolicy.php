<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WaterConnection;

/**
 * Phase-94 WATER-CLIENTS-FOUNDATION: water connections are landlord-owned (the
 * supplier). A caretaker may view their landlord's connections; the water-client
 * account, once onboarded (Phase 95), may view its own. Lifecycle is landlord-only.
 */
class WaterConnectionPolicy
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

    public function view(User $user, WaterConnection $connection): bool
    {
        if ($user->isLandlord()) {
            return $connection->landlord_id === $user->id;
        }
        if ($user->isCaretaker()) {
            return $connection->landlord_id === $user->landlord_id;
        }
        if ($user->isWaterClient()) {
            return $connection->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, WaterConnection $connection): bool
    {
        return $user->isLandlord() && $connection->landlord_id === $user->id;
    }

    public function delete(User $user, WaterConnection $connection): bool
    {
        return $this->update($user, $connection);
    }
}
