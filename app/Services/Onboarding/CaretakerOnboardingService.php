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
 * Phase-77 CARETAKER-FLOW-1: OnboardingFlow::forRole('caretaker') now declares
 * 5 steps (welcome + orientation bookends, matching the landlord flow):
 *   1 → Welcome                  (intro; no-op advance)
 *   2 → Profile                  (User: name + mobile_number)
 *   3 → Building assignment      (accept/decline pending assignments)
 *   4 → Notification preferences (NotificationPreference upsert)
 *   5 → Orientation              (building summary + first-task hand-off; no-op
 *                                advance, the controller resolves the redirect)
 */
class CaretakerOnboardingService implements OnboardingStepProcessor
{
    public function __construct(
        protected CaretakerAssignmentService $assignmentService,
    ) {}

    public function processStep(int $step, array $data, User $user, OnboardingProgress $progress): bool
    {
        return match ($step) {
            1 => true, // Welcome — acknowledged by the session advance.
            2 => $this->processProfile($data, $user),
            3 => $this->processBuildingAssignmentAck($data, $user),
            4 => $this->processNotificationPreferences($data, $user),
            5 => true, // Orientation — hand-off only; no canonical write.
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
