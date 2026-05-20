<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Phase-66 NPS-SURVEY-1: any authenticated landlord/caretaker/tenant
 * may submit their own NPS response.
 *
 * Super-admins are omnipotent via the global Gate::before, so they
 * cannot (and need not) be blocked at the policy layer — the actual
 * "ops users are not surveyed" guarantee lives in
 * NpsEligibilityService::shouldPrompt, which never returns a prompt for
 * a super-admin. before() mirrors the LegalHoldPolicy convention.
 */
class NpsResponsePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }
}
