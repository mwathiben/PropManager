<?php

declare(strict_types=1);

namespace App\Onboarding;

use InvalidArgumentException;

/**
 * Phase-46 WIZARD-INFRA-2: per-role step sequence dispatcher.
 *
 * The OnboardingController hardcoded an 8-step landlord sequence pre-Phase-46;
 * caretakers had no onboarding flow at all; tenants had a separate
 * /tenant/complete-profile flow that didn't share infrastructure.
 *
 * OnboardingFlow::forRole($role) returns a value object exposing
 * firstStep(), nextStep($current), allSteps(), isComplete($currentStep),
 * stepLabel($step). The controller delegates to this; tests can stub
 * a flow without touching the database.
 */
final class OnboardingFlow
{
    /**
     * @param  list<int>  $steps  Ordered step numbers in this flow
     * @param  array<int, string>  $labels  Map step => label
     */
    private function __construct(
        public readonly string $role,
        public readonly array $steps,
        public readonly array $labels,
    ) {}

    public static function forRole(string $role): self
    {
        return match ($role) {
            'landlord' => new self(
                role: 'landlord',
                steps: [1, 2, 3, 4, 5, 6, 7, 8],
                labels: [
                    1 => 'Welcome',
                    2 => 'Profile',
                    3 => 'First property',
                    4 => 'Units + building',
                    5 => 'Payment configuration',
                    6 => 'Invite team',
                    7 => 'First tenant',
                    8 => 'Done',
                ],
            ),
            'caretaker' => new self(
                role: 'caretaker',
                steps: [1, 2, 3, 4, 5],
                labels: [
                    1 => 'Welcome',
                    2 => 'Profile',
                    3 => 'Building assignment',
                    4 => 'Notification preferences',
                    5 => 'Orientation',
                ],
            ),
            'tenant' => new self(
                role: 'tenant',
                steps: [1, 2, 3],
                labels: [
                    1 => 'Profile',
                    2 => 'KYC verification',
                    3 => 'Payment method',
                ],
            ),
            // Phase-95 WATER-CLIENT-ONBOARDING: tenant onboarding minus the lease —
            // profile, required documents, then payment method.
            'water_client' => new self(
                role: 'water_client',
                steps: [1, 2, 3],
                labels: [
                    1 => 'Profile',
                    2 => 'Documents',
                    3 => 'Payment method',
                ],
            ),
            default => throw new InvalidArgumentException("Unsupported onboarding role: {$role}"),
        };
    }

    public function firstStep(): int
    {
        return $this->steps[0];
    }

    public function nextStep(int $current): ?int
    {
        $idx = array_search($current, $this->steps, true);
        if ($idx === false || $idx === array_key_last($this->steps)) {
            return null;
        }

        return $this->steps[$idx + 1];
    }

    public function previousStep(int $current): ?int
    {
        $idx = array_search($current, $this->steps, true);
        if ($idx === false || $idx === 0) {
            return null;
        }

        return $this->steps[$idx - 1];
    }

    public function isComplete(int $currentStep): bool
    {
        return $currentStep === array_key_last($this->steps) + $this->firstStep();
    }

    public function stepLabel(int $step): string
    {
        return $this->labels[$step] ?? "Step {$step}";
    }

    /** @return list<int> */
    public function allSteps(): array
    {
        return $this->steps;
    }

    public function lastStep(): int
    {
        return $this->steps[array_key_last($this->steps)];
    }

    public function isValidStep(int $step): bool
    {
        return in_array($step, $this->steps, true);
    }
}
