<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ManagementAgreement;
use App\Models\User;

/**
 * Slice-2: management agreements are a manager concept (a manager runs
 * properties on owners' behalf). Composing/viewing is manager-only; view is
 * additionally ownership-checked (TenantScope already hides other accounts').
 */
class ManagementAgreementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, ManagementAgreement $agreement): bool
    {
        return $user->isManager() && (int) $agreement->landlord_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function send(User $user, ManagementAgreement $agreement): bool
    {
        return $user->isManager() && (int) $agreement->landlord_id === (int) $user->id;
    }
}
