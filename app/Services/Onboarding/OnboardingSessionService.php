<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\OnboardingSession;
use App\Onboarding\OnboardingFlow;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase-46 WIZARD-INFRA-3: transactional advance/back semantics for
 * the OnboardingSession lifecycle.
 *
 * - advance(): wraps the caller's write closure in DB::transaction
 *   so a half-succeeded canonical write doesn't leave the wizard
 *   pointing at an inconsistent step. On success, current_step
 *   moves forward + step_history captures the transition + last_touched_at
 *   bumps.
 *
 * - back(): NEVER touches canonical models — the user can revisit
 *   the previous step's form to edit, but canonical rows don't roll
 *   back. step_history captures the back-navigation with action='back'.
 *   This is the explicit decision behind WIZARD-INFRA-3.
 *
 * - complete(): final step transition. completed_at = now().
 */
class OnboardingSessionService
{
    public function advance(OnboardingSession $session, int $targetStep, Closure $writer): OnboardingSession
    {
        $flow = OnboardingFlow::forRole($session->role);

        if (! $flow->isValidStep($targetStep)) {
            throw new InvalidArgumentException("Step {$targetStep} not in flow for role {$session->role}.");
        }
        if ($targetStep <= $session->current_step) {
            throw new InvalidArgumentException(
                "advance() must move forward; use back() for {$targetStep} <= {$session->current_step}."
            );
        }

        return DB::transaction(function () use ($session, $targetStep, $writer): OnboardingSession {
            // Run the caller's canonical-model writes first. If $writer
            // throws, the transaction rolls back + the session row
            // does NOT advance.
            $writer($session);

            $session->refresh();
            $session->update([
                'current_step' => $targetStep,
                'step_history' => array_merge((array) $session->step_history, [[
                    'step' => $targetStep,
                    'action' => 'advance',
                    'at' => now()->toIso8601String(),
                ]]),
                'last_touched_at' => now(),
            ]);

            return $session->fresh();
        });
    }

    public function back(OnboardingSession $session, int $targetStep): OnboardingSession
    {
        $flow = OnboardingFlow::forRole($session->role);

        if (! $flow->isValidStep($targetStep)) {
            throw new InvalidArgumentException("Step {$targetStep} not in flow for role {$session->role}.");
        }
        if ($targetStep >= $session->current_step) {
            throw new InvalidArgumentException(
                "back() must move backward; use advance() for {$targetStep} >= {$session->current_step}."
            );
        }

        // No canonical-model rollback — by design.
        $session->update([
            'current_step' => $targetStep,
            'step_history' => array_merge((array) $session->step_history, [[
                'step' => $targetStep,
                'action' => 'back',
                'at' => now()->toIso8601String(),
            ]]),
            'last_touched_at' => now(),
        ]);

        return $session->fresh();
    }

    public function complete(OnboardingSession $session): OnboardingSession
    {
        $flow = OnboardingFlow::forRole($session->role);

        $session->update([
            'current_step' => $flow->lastStep(),
            'completed_at' => now(),
            'last_touched_at' => now(),
            'step_history' => array_merge((array) $session->step_history, [[
                'step' => $flow->lastStep(),
                'action' => 'completed',
                'at' => now()->toIso8601String(),
            ]]),
        ]);

        return $session->fresh();
    }

    public function markAbandoned(OnboardingSession $session): OnboardingSession
    {
        $session->update([
            'abandoned_at' => now(),
            'step_history' => array_merge((array) $session->step_history, [[
                'step' => $session->current_step,
                'action' => 'abandoned',
                'at' => now()->toIso8601String(),
            ]]),
        ]);

        return $session->fresh();
    }
}
