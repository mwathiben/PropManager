<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\OnboardingProgress;
use App\Models\User;

/**
 * Phase-47 ROLE-DISPATCH-2: tenant onboarding step processor.
 *
 * OnboardingFlow::forRole('tenant') declares 3 steps:
 *   1 → Profile           (User: name + mobile_number + national_id)
 *   2 → KYC verification  (acknowledgement; actual document upload is the
 *                         existing /tenant/kyc surface from Phase 13)
 *   3 → Payment method    (acknowledgement; actual stored payment methods
 *                         arrive in a later cycle)
 *
 * Scope is intentionally minimal — Phase 48+ deepens. The point of Phase 47
 * is that a tenant reaching /onboarding/step/N now hits role-appropriate
 * processing instead of a landlord form that 422s on submit.
 */
class TenantOnboardingService implements OnboardingStepProcessor
{
    public function processStep(int $step, array $data, User $user, OnboardingProgress $progress): bool
    {
        return match ($step) {
            1 => $this->processProfile($data, $user),
            2 => $this->processKycAcknowledgement($data, $user),
            3 => $this->processPaymentMethodAcknowledgement($data, $user),
            default => false,
        };
    }

    private function processProfile(array $data, User $user): bool
    {
        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'mobile_number' => $data['mobile_number'] ?? null,
            'national_id' => $data['national_id'] ?? null,
        ], fn ($v) => $v !== null));

        return true;
    }

    private function processKycAcknowledgement(array $data, User $user): bool
    {
        // Phase-48 TENANT-KYC-BRIDGE-2: gate advance on having submitted
        // every required KYC requirement. Pending review is acceptable —
        // a tenant should be able to finish the wizard while the landlord
        // reviews; the gate just ensures they've actually uploaded.
        // Document upload itself happens at /complete-profile.
        $progress = $user->kycProgress();

        return $progress['submitted'] >= $progress['required'];
    }

    private function processPaymentMethodAcknowledgement(array $data, User $user): bool
    {
        // Tenant confirms they understand which payment channels their
        // landlord accepts. Stored payment methods land in a later cycle.
        return true;
    }
}
