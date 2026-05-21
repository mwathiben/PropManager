<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaseCoTenant;
use App\Models\User;

/**
 * Phase-83 CO-TENANT-3: only the owning landlord (or their caretaker) may
 * manage a lease's co-tenants.
 */
class LeaseCoTenantPolicy
{
    private function ownsLease(User $user, LeaseCoTenant $coTenant): bool
    {
        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $coTenant->landlord_id === $landlordId;
    }

    public function delete(User $user, LeaseCoTenant $coTenant): bool
    {
        return $this->ownsLease($user, $coTenant);
    }
}
