<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\OnboardingMilestone;
use App\Models\User;
use App\Models\UserTourState;

/**
 * Phase-66 ONBOARDING-TOUR-1: the tour engine.
 *
 * Owns the per-role tour registry and is the single authority on whether
 * a tour shows and which steps it contains. Two ideas drive it:
 *
 *  - **Milestone-aware steps.** A landlord step that teaches "add your
 *    first building" is dropped once the FIRST_PROPERTY milestone is
 *    reached — the tour only ever shows what the user hasn't done yet.
 *  - **Terminal state.** Once {@see UserTourState} is completed or
 *    dismissed, payloadFor() returns null forever — the client can never
 *    re-trigger a finished tour by replaying props.
 *
 * The registry is data, not code: a new tour drops in as a REGISTRY
 * entry + lang copy with no engine changes.
 */
class TourService
{
    /**
     * tour_key => [role, steps[]]. Each step:
     *   key    — identity + i18n suffix (onboarding.tour.<tour>.<key>.*)
     *   target — the [data-tour] anchor the spotlight highlights
     *   gate   — (optional) OnboardingMilestone const; step is skipped
     *            once that milestone is reached
     *   route  — (optional) route name the step deep-links to
     */
    private const REGISTRY = [
        'landlord-dashboard' => [
            'role' => 'landlord',
            'steps' => [
                ['key' => 'welcome', 'target' => 'nav-dashboard'],
                ['key' => 'add_building', 'target' => 'nav-buildings', 'gate' => OnboardingMilestone::FIRST_PROPERTY, 'route' => 'buildings.index'],
                ['key' => 'add_unit', 'target' => 'nav-buildings', 'gate' => OnboardingMilestone::FIRST_UNIT],
                ['key' => 'invite_tenant', 'target' => 'nav-tenants', 'gate' => OnboardingMilestone::FIRST_TENANT, 'route' => 'tenants.hub'],
                ['key' => 'create_invoice', 'target' => 'nav-finances', 'gate' => OnboardingMilestone::FIRST_INVOICE, 'route' => 'finances.index'],
                ['key' => 'record_payment', 'target' => 'nav-finances', 'gate' => OnboardingMilestone::FIRST_PAYMENT],
            ],
        ],
        'caretaker-intro' => [
            'role' => 'caretaker',
            'steps' => [
                ['key' => 'welcome', 'target' => 'nav-dashboard'],
                ['key' => 'tickets', 'target' => 'nav-tickets'],
                ['key' => 'finish', 'target' => 'nav-dashboard'],
            ],
        ],
        'tenant-intro' => [
            'role' => 'tenant',
            'steps' => [
                ['key' => 'welcome', 'target' => 'nav-dashboard'],
                ['key' => 'finances', 'target' => 'nav-tenant-finances', 'route' => 'tenant.finances.index'],
                ['key' => 'inbox', 'target' => 'nav-inbox'],
            ],
        ],
    ];

    public function tourKeyForRole(?string $role): ?string
    {
        return match ($role) {
            'landlord' => 'landlord-dashboard',
            'caretaker' => 'caretaker-intro',
            'tenant' => 'tenant-intro',
            default => null,
        };
    }

    public function isKnownTour(string $tourKey): bool
    {
        return array_key_exists($tourKey, self::REGISTRY);
    }

    /**
     * The ordered, milestone-filtered, copy-decorated steps for a tour.
     * Steps whose gating milestone is already reached are dropped.
     *
     * @return list<array{key:string, target:string, route:?string, title:string, body:string}>
     */
    public function stepsFor(User $user, string $tourKey): array
    {
        $definition = self::REGISTRY[$tourKey] ?? null;
        if ($definition === null) {
            return [];
        }

        $reached = $this->reachedMilestones($user);

        $steps = [];
        foreach ($definition['steps'] as $step) {
            if (isset($step['gate']) && in_array($step['gate'], $reached, true)) {
                continue;
            }

            $steps[] = [
                'key' => $step['key'],
                'target' => $step['target'],
                'route' => isset($step['route']) ? route($step['route']) : null,
                'title' => __("onboarding.tour.{$tourKey}.{$step['key']}.title"),
                'body' => __("onboarding.tour.{$tourKey}.{$step['key']}.body"),
            ];
        }

        return $steps;
    }

    /**
     * Inertia payload for the user's active tour, or null when: the role
     * has no tour, the state is terminal, or milestone progress has left
     * no steps to show.
     *
     * @return array{tour_key:string, active:bool, current_step:int, steps:list<array<string,mixed>>}|null
     */
    public function payloadFor(User $user): ?array
    {
        $tourKey = $this->tourKeyForRole($user->role);
        if ($tourKey === null) {
            return null;
        }

        $state = UserTourState::query()
            ->where('user_id', $user->id)
            ->where('tour_key', $tourKey)
            ->first();

        if ($state !== null && $state->isTerminal()) {
            return null;
        }

        $steps = $this->stepsFor($user, $tourKey);
        if ($steps === []) {
            return null;
        }

        // Clamp the resume cursor: milestone filtering can shorten the
        // list after the cursor was last persisted.
        $currentStep = max(0, min($state?->current_step ?? 0, count($steps) - 1));

        return [
            'tour_key' => $tourKey,
            'active' => true,
            'current_step' => $currentStep,
            'steps' => $steps,
        ];
    }

    public function advance(User $user, string $tourKey, int $step): UserTourState
    {
        $state = $this->liveState($user, $tourKey);
        if ($state->isTerminal()) {
            return $state;
        }

        // Monotonic: a replayed earlier step can't rewind the cursor.
        $state->current_step = max($state->current_step, max(0, $step));
        $state->last_advanced_at = now();
        $state->save();

        return $state;
    }

    public function complete(User $user, string $tourKey): UserTourState
    {
        return $this->terminate($user, $tourKey, UserTourState::STATUS_COMPLETED, 'completed_at');
    }

    public function dismiss(User $user, string $tourKey): UserTourState
    {
        return $this->terminate($user, $tourKey, UserTourState::STATUS_DISMISSED, 'dismissed_at');
    }

    private function terminate(User $user, string $tourKey, string $status, string $stampColumn): UserTourState
    {
        $state = $this->liveState($user, $tourKey);
        if ($state->isTerminal()) {
            return $state;
        }

        $state->status = $status;
        $state->{$stampColumn} = now();
        $state->save();

        return $state;
    }

    private function liveState(User $user, string $tourKey): UserTourState
    {
        return UserTourState::firstOrCreate(
            ['user_id' => $user->id, 'tour_key' => $tourKey],
            [
                'status' => UserTourState::STATUS_ACTIVE,
                'current_step' => 0,
                'started_at' => now(),
            ],
        );
    }

    /**
     * Milestones reached for the user's landlord context. Only landlord
     * tours gate on these; caretaker/tenant tours have no gates, so the
     * empty fallback for a missing landlord_id is harmless.
     *
     * @return list<string>
     */
    private function reachedMilestones(User $user): array
    {
        $landlordId = $user->isScopeOwner() ? $user->id : $user->landlord_id;
        if ($landlordId === null) {
            return [];
        }

        return OnboardingMilestone::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->pluck('milestone')
            ->all();
    }
}
