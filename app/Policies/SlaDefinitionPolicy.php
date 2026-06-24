<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SlaDefinition;
use App\Models\User;

/**
 * Phase-54 SLA-LANDLORD-UI-3: landlord-scoped CRUD on SlaDefinition.
 *
 * Platform-default rows (landlord_id NULL) are read-only from the
 * landlord-facing surface — they're authored by super-admins via a
 * separate /admin path. A landlord can create their OWN overrides
 * and edit/delete only those.
 */
class SlaDefinitionPolicy
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

    public function view(User $user, SlaDefinition $sla): bool
    {
        // Scope owners see their own rows + the read-only global cascade.
        return $user->isScopeOwner()
            && ($sla->landlord_id === null || $sla->landlord_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function update(User $user, SlaDefinition $sla): bool
    {
        // Cannot edit platform defaults — only your own overrides.
        return $user->isScopeOwner() && $sla->landlord_id === $user->id;
    }

    public function delete(User $user, SlaDefinition $sla): bool
    {
        return $user->isScopeOwner() && $sla->landlord_id === $user->id;
    }
}
