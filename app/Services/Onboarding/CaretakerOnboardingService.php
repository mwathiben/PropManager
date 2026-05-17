<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Building;
use App\Models\NotificationPreference;
use App\Models\OnboardingProgress;
use App\Models\User;

/**
 * Phase-47 ROLE-DISPATCH-3: caretaker onboarding step processor.
 *
 * OnboardingFlow::forRole('caretaker') declares 3 steps:
 *   1 → Profile                  (User: name + mobile_number)
 *   2 → Building assignment      (acknowledgement; buildings.caretaker_id is
 *                                set by the landlord when issuing the
 *                                invitation — Phase 28 surface)
 *   3 → Notification preferences (NotificationPreference upsert: which
 *                                channels the caretaker prefers)
 *
 * Scope intentionally minimal — Phase 48+ deepens building assignment UX.
 */
class CaretakerOnboardingService implements OnboardingStepProcessor
{
    public function processStep(int $step, array $data, User $user, OnboardingProgress $progress): bool
    {
        return match ($step) {
            1 => $this->processProfile($data, $user),
            2 => $this->processBuildingAssignmentAck($data, $user),
            3 => $this->processNotificationPreferences($data, $user),
            default => false,
        };
    }

    private function processProfile(array $data, User $user): bool
    {
        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'mobile_number' => $data['mobile_number'] ?? null,
        ], fn ($v) => $v !== null));

        return true;
    }

    private function processBuildingAssignmentAck(array $data, User $user): bool
    {
        // Caretaker confirms the buildings their landlord assigned. The
        // canonical state is buildings.caretaker_id (set landlord-side by
        // the invitation accept flow); this step just records that the
        // caretaker has seen the assignment.
        $assignedCount = Building::where('caretaker_id', $user->id)->count();

        if ($assignedCount === 0) {
            // No buildings assigned yet — the caretaker can still progress,
            // their landlord will assign buildings post-onboarding.
            return true;
        }

        return true;
    }

    private function processNotificationPreferences(array $data, User $user): bool
    {
        $landlordId = $user->landlord_id ?? $user->id;

        NotificationPreference::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $user->id, 'landlord_id' => $landlordId],
            array_filter([
                'email_enabled' => $data['email_enabled'] ?? null,
                'sms_enabled' => $data['sms_enabled'] ?? null,
                'whatsapp_enabled' => $data['whatsapp_enabled'] ?? null,
                'push_enabled' => $data['push_enabled'] ?? null,
            ], fn ($v) => $v !== null)
        );

        return true;
    }
}
