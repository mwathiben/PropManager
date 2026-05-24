<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WaterClientCharge;

/**
 * Phase-97 WATER-CLIENT-BILLING: water-client charges are landlord-owned (the
 * supplier bills). A caretaker may view the landlord's charges; the water-client
 * account may view its own. Recording a payment is landlord-only.
 */
class WaterClientChargePolicy
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
        return $user->isLandlord() || $user->isCaretaker() || $user->isWaterClient();
    }

    public function view(User $user, WaterClientCharge $charge): bool
    {
        if ($user->isLandlord()) {
            return $charge->landlord_id === $user->id;
        }
        if ($user->isCaretaker()) {
            return $charge->landlord_id === $user->landlord_id;
        }
        if ($user->isWaterClient()) {
            return $charge->connection?->user_id === $user->id;
        }

        return false;
    }

    public function recordPayment(User $user, WaterClientCharge $charge): bool
    {
        return $user->isLandlord() && $charge->landlord_id === $user->id;
    }
}
