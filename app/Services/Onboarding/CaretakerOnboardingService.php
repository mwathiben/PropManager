<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Building;
use App\Models\CaretakerAssignment;
use App\Models\NotificationPreference;
use App\Models\OnboardingProgress;
use App\Models\User;
use App\Services\Caretaker\CaretakerAssignmentService;

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
    public function __construct(
        protected CaretakerAssignmentService $assignmentService,
    ) {
    }

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
        // Phase-48 CARETAKER-ASSIGNMENT-UX-3: walk pending assignments,
        // flip to accepted/declined based on form input. Acceptance is
        // the default — only explicitly-declined ids hit decline().
        // If no pending rows exist, the step is a no-op (advance).
        $pending = CaretakerAssignment::query()
            ->where('caretaker_id', $user->id)
            ->pending()
            ->get();

        if ($pending->isEmpty()) {
            return true;
        }

        $declineIds = collect($data['decline'] ?? [])->map(fn ($v) => (int) $v)->all();
        $reasons = $data['decline_reason'] ?? [];

        foreach ($pending as $assignment) {
            if (in_array($assignment->building_id, $declineIds, true)) {
                $reason = $reasons[$assignment->building_id] ?? null;
                $this->assignmentService->decline($assignment, $reason);
            } else {
                $this->assignmentService->accept($assignment);
            }
        }

        return true;
    }

    private function processNotificationPreferences(array $data, User $user): bool
    {
        // Phase-48 CARETAKER-NOTIF-PREFS-1: writes per-type columns in
        // addition to channel toggles. The caretaker-relevant type subset
        // comes from NotificationPreference::caretakerTypes() so the Vue
        // form + service stay in sync.
        $landlordId = $user->landlord_id ?? $user->id;

        $writes = [
            'email_enabled' => $data['email_enabled'] ?? null,
            'sms_enabled' => $data['sms_enabled'] ?? null,
            'whatsapp_enabled' => $data['whatsapp_enabled'] ?? null,
            'push_enabled' => $data['push_enabled'] ?? null,
        ];

        foreach (NotificationPreference::caretakerTypes() as $typeCol) {
            $writes[$typeCol] = $data[$typeCol] ?? null;
        }

        NotificationPreference::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $user->id, 'landlord_id' => $landlordId],
            array_filter($writes, fn ($v) => $v !== null)
        );

        return true;
    }
}
