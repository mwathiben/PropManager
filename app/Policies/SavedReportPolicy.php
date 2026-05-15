<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavedReport;
use App\Models\User;

/**
 * Phase-27 BI-BUILDER-1: only the owning landlord may view + modify
 * their saved reports. Super-admin bypass + DPA-4 restriction are
 * handled by AuthServiceProvider::Gate::before so they don't need to
 * appear here.
 */
class SavedReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker();
    }

    public function view(User $user, SavedReport $report): bool
    {
        return (int) $report->landlord_id === $this->landlordIdOf($user);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, SavedReport $report): bool
    {
        return $user->isLandlord() && (int) $report->landlord_id === (int) $user->id;
    }

    public function delete(User $user, SavedReport $report): bool
    {
        return $user->isLandlord() && (int) $report->landlord_id === (int) $user->id;
    }

    /**
     * For a caretaker the "owner" is their landlord; for a landlord
     * it's themselves. Tenants are never granted access (viewAny
     * already filters at the gate).
     */
    private function landlordIdOf(User $user): int
    {
        return $user->isLandlord()
            ? (int) $user->id
            : (int) ($user->landlord_id ?? 0);
    }
}
