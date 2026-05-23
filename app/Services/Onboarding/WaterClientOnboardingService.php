<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\OnboardingProgress;
use App\Models\User;
use App\Services\Tenant\TenantPaymentMethodService;

/**
 * Phase-95 WATER-CLIENT-ONBOARDING: water-client onboarding step processor —
 * tenant onboarding minus the lease. OnboardingFlow::forRole('water_client')
 * declares 3 steps:
 *   1 → Profile         (name + mobile_number + national_id)
 *   2 → Documents       (acknowledgement; required-document upload arrives later)
 *   3 → Payment method  (reuses the shared, user-agnostic payment-method store)
 */
class WaterClientOnboardingService implements OnboardingStepProcessor
{
    public function __construct(
        protected TenantPaymentMethodService $paymentMethodService,
    ) {}

    public function processStep(int $step, array $data, User $user, OnboardingProgress $progress): bool
    {
        return match ($step) {
            1 => $this->processProfile($data, $user),
            2 => true,
            3 => $this->processPaymentMethod($data, $user),
            default => false,
        };
    }

    private function processProfile(array $data, User $user): bool
    {
        // Drop blanks too: an empty national_id would otherwise be encrypted as ''
        // and a blank mobile_number would wipe an existing value on a re-submit.
        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'mobile_number' => $data['mobile_number'] ?? null,
            'national_id' => $data['national_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        return true;
    }

    private function processPaymentMethod(array $data, User $user): bool
    {
        $type = $data['type'] ?? null;
        $details = $data['details'] ?? null;

        if ($type === null || $details === null || ! is_array($details) || $details === []) {
            return true;
        }

        $this->paymentMethodService->store($user, $type, $details, (bool) ($data['is_default'] ?? false));

        return true;
    }
}
