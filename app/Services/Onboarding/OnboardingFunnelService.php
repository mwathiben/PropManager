<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\OnboardingSession;
use App\Onboarding\OnboardingFlow;

/**
 * Phase-77 FUNNEL-1: per-role onboarding step funnel computed from
 * onboarding_sessions (current_step + completed_at/abandoned_at). A session is
 * counted as having "reached" step N when current_step >= N or it completed.
 * Pure grouped counts — no step_history JSON parsing. Platform-wide
 * (super-admin); onboarding_sessions carries no landlord scope.
 */
class OnboardingFunnelService
{
    private const ROLES = ['landlord', 'caretaker', 'tenant'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $out = [];
        foreach (self::ROLES as $role) {
            $out[$role] = $this->forRole($role);
        }

        return $out;
    }

    /**
     * @return array{role:string, total:int, completed:int, abandoned:int, active:int, completion_rate:float, steps:list<array{step:int,label:string,reached:int}>, drop_off_step:?int, drop_off_count:int}
     */
    public function forRole(string $role): array
    {
        $flow = OnboardingFlow::forRole($role);

        $base = OnboardingSession::query()->where('role', $role);

        $total = (clone $base)->count();
        $completed = (clone $base)->whereNotNull('completed_at')->count();
        $abandoned = (clone $base)->whereNotNull('abandoned_at')->count();
        $active = max(0, $total - $completed - $abandoned);

        $steps = [];
        foreach ($flow->allSteps() as $step) {
            $reached = (clone $base)
                ->where(function ($q) use ($step) {
                    $q->where('current_step', '>=', $step)->orWhereNotNull('completed_at');
                })
                ->count();
            $steps[] = ['step' => $step, 'label' => $flow->stepLabel($step), 'reached' => $reached];
        }

        $dropOffStep = null;
        $dropOffCount = 0;
        for ($i = 0; $i < count($steps) - 1; $i++) {
            $drop = $steps[$i]['reached'] - $steps[$i + 1]['reached'];
            if ($drop > $dropOffCount) {
                $dropOffCount = $drop;
                $dropOffStep = $steps[$i]['step'];
            }
        }

        return [
            'role' => $role,
            'total' => $total,
            'completed' => $completed,
            'abandoned' => $abandoned,
            'active' => $active,
            'completion_rate' => $total > 0 ? round($completed / $total * 100, 1) : 0.0,
            'steps' => $steps,
            'drop_off_step' => $dropOffStep,
            'drop_off_count' => $dropOffCount,
        ];
    }
}
