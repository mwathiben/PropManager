<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\OnboardingProgress;
use App\Models\User;

/**
 * Phase-47 ROLE-DISPATCH: per-role step processor contract. OnboardingController
 * resolves the right implementation based on $user->role and forwards the
 * validated step data to processStep().
 *
 * Each implementation handles its own role-specific canonical writes. The
 * controller wraps the processStep call in OnboardingSessionService::advance
 * (or ::writeAt for re-edits) so the transactional discipline is invariant
 * across roles.
 */
interface OnboardingStepProcessor
{
    public function processStep(int $step, array $data, User $user, OnboardingProgress $progress): bool;
}
