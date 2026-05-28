<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-31 ONB-WIZARD-2: lightweight read-only status endpoint for the
 * dashboard ResumeBanner.vue. Returns null when the user has no progress
 * record or has already completed onboarding — the banner only renders
 * when there is genuinely an unfinished wizard to resume.
 */
class OnboardingResumeController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $progress = $user->onboardingProgress;

        if ($progress === null || $progress->is_complete) {
            return response()->json(null);
        }

        return response()->json([
            'current_step' => $progress->current_step,
            'total_steps' => $progress->total_steps,
            'current_step_name' => $progress->current_step_name,
            'completion_pct' => $progress->progress_percentage,
            'last_touched_at' => $progress->last_touched_at?->toIso8601String(),
            'started_at' => $progress->started_at?->toIso8601String(),
            'resume_url' => route('onboarding.step', ['step' => $progress->current_step]),
        ]);
    }
}
