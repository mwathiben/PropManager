<?php

declare(strict_types=1);

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\ReferralLeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-66 REFERRAL-LEADERBOARD-2: landlord-facing leaderboard.
 *
 * ALWAYS anonymised — a landlord may see only their own identity (the
 * is_self row), never another landlord's. The viewer's own rank is
 * surfaced separately even when it falls outside the visible top-N.
 */
class ReferralLeaderboardController extends Controller
{
    public function __construct(private ReferralLeaderboardService $leaderboard) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Growth/Leaderboard', [
            'leaderboard' => $this->leaderboard->topReferrers(
                limit: 20,
                anonymise: true,
                viewerId: (int) $user->id,
            ),
            'opted_out' => (bool) $user->leaderboard_opt_out,
        ]);
    }
}
