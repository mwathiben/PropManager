<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AgreementSignature;
use App\Models\User;

/**
 * Slice-2 PR-2.3c: an agreement signature (the owner's e-sign invitation +
 * evidence) is a manager concept, like the agreement it belongs to. The public
 * signing flow is token-gated (no policy). Manager-facing access is manager-only
 * and ownership-checked; TenantScope already hides other accounts'.
 */
class AgreementSignaturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, AgreementSignature $signature): bool
    {
        return $user->isManager() && (int) $signature->landlord_id === (int) $user->id;
    }
}
