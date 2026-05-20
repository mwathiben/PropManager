<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use App\Services\Onboarding\TourService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase-66 ONBOARDING-TOUR-2: server-authoritative tour progress.
 *
 * The tour_key is derived from the user's role, never taken from the
 * client, so a request can only mutate the caller's own role tour. All
 * three actions no-op once the tour is terminal (TourService rejects the
 * write), so a replayed request cannot resurrect a finished tour.
 */
class OnboardingTourController extends Controller
{
    public function __construct(private TourService $tours) {}

    public function advance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $this->withTour($request, fn (string $key) => $this->tours->advance(
            $request->user(),
            $key,
            (int) $validated['step'],
        ));

        return back(303);
    }

    public function complete(Request $request, MetricsService $metrics): RedirectResponse
    {
        $tourKey = $this->tours->tourKeyForRole($request->user()->role);
        if ($tourKey !== null) {
            $state = $this->tours->complete($request->user(), $tourKey);
            // Phase-66 GROWTH-OBSERVABILITY-2: real-time counter, bumped only
            // on a genuine transition (never on a replay of a finished tour).
            if ($state->wasChanged('status')) {
                $metrics->increment('onboarding_tour_completed_total');
            }
        }

        return back(303);
    }

    public function dismiss(Request $request, MetricsService $metrics): RedirectResponse
    {
        $tourKey = $this->tours->tourKeyForRole($request->user()->role);
        if ($tourKey !== null) {
            $state = $this->tours->dismiss($request->user(), $tourKey);
            if ($state->wasChanged('status')) {
                $metrics->increment('onboarding_tour_dismissed_total');
            }
        }

        return back(303);
    }

    /**
     * Resolve the caller's role tour and run $action with its key. No-op
     * for roles without a tour (e.g. super_admin).
     */
    private function withTour(Request $request, callable $action): void
    {
        $tourKey = $this->tours->tourKeyForRole($request->user()->role);
        if ($tourKey !== null) {
            $action($tourKey);
        }
    }
}
