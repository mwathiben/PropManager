<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Meter;
use App\Models\User;

/**
 * Phase-86 WATER-METER-FOUNDATION: meters are landlord-owned. Caretakers may
 * VIEW the meters of the landlord they serve (they record readings against
 * them) but lifecycle actions (create / replace / decommission) are landlord-only.
 */
class MeterPolicy
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

    public function view(User $user, Meter $meter): bool
    {
        if ($user->isScopeOwner()) {
            return $meter->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $meter->landlord_id === $user->landlord_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function update(User $user, Meter $meter): bool
    {
        return $user->isScopeOwner() && $meter->landlord_id === $user->id;
    }

    public function replace(User $user, Meter $meter): bool
    {
        return $this->update($user, $meter);
    }

    public function decommission(User $user, Meter $meter): bool
    {
        return $this->update($user, $meter);
    }

    public function disconnect(User $user, Meter $meter): bool
    {
        return $this->update($user, $meter);
    }

    public function reconnect(User $user, Meter $meter): bool
    {
        return $this->update($user, $meter);
    }

    public function delete(User $user, Meter $meter): bool
    {
        return $this->update($user, $meter);
    }
}
