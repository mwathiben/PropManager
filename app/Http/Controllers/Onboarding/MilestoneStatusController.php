<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\OnboardingMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-31 ONB-EMPTY-1: backs the MilestoneChecklist Vue component
 * with a flat boolean map of which funnel steps have fired for the
 * authed landlord. Tenant role gets an empty payload (the checklist
 * is landlord-facing).
 *
 * Also exposes the per-user dismiss flag (ONB-EMPTY-3) so the
 * checklist can hide itself once the operator dismisses it OR every
 * step is complete.
 */
class MilestoneStatusController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'landlord') {
            return response()->json([]);
        }

        $hits = OnboardingMilestone::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $user->id)
            ->pluck('milestone')
            ->all();

        $map = [];
        foreach (OnboardingMilestone::FUNNEL as $m) {
            $map[$m] = in_array($m, $hits, true);
        }
        $map['dismissed_at'] = $user->onboarding_checklist_dismissed_at?->toIso8601String();

        return response()->json($map);
    }

    public function dismiss(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->onboarding_checklist_dismissed_at = now();
        $user->save();

        return response()->json(['dismissed_at' => $user->onboarding_checklist_dismissed_at->toIso8601String()]);
    }
}
