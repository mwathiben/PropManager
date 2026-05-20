<?php

declare(strict_types=1);

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\ReferralLeaderboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase-66 REFERRAL-LEADERBOARD-3: DPA opt-out toggle. Persists the
 * user's preference and rolls the leaderboard cache so the change takes
 * effect on the next view.
 */
class LeaderboardOptOutController extends Controller
{
    public function __construct(private ReferralLeaderboardService $leaderboard) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'opt_out' => ['required', 'boolean'],
        ]);

        $request->user()->update(['leaderboard_opt_out' => $validated['opt_out']]);

        $this->leaderboard->flushCache();

        return back(303)->with('success', __('growth.leaderboard.opt_out_saved'));
    }
}
