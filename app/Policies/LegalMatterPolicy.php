<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LegalMatter;
use App\Models\User;

/**
 * Phase-72 MATTER-GROUPING: only the owning landlord (or a super admin in
 * cross-tenant ops mode) may view/release/close a matter. LegalMatter is
 * TenantScope-bound so route binding already 404s foreign matters; these gates
 * are defense-in-depth + the role restriction.
 */
class LegalMatterPolicy
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

    public function view(User $user, LegalMatter $matter): bool
    {
        return $this->owns($user, $matter);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function release(User $user, LegalMatter $matter): bool
    {
        return $this->owns($user, $matter);
    }

    public function close(User $user, LegalMatter $matter): bool
    {
        return $this->owns($user, $matter);
    }

    public function auditExport(User $user, LegalMatter $matter): bool
    {
        return $this->owns($user, $matter);
    }

    private function owns(User $user, LegalMatter $matter): bool
    {
        return $user->isLandlord() && (int) $matter->landlord_id === (int) $user->id;
    }
}
