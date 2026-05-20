<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\Growth\ReferralLeaderboardService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-66 REFERRAL-LEADERBOARD-2: super-admin leaderboard with full
 * names for ops/support. Gated to role:super_admin at the route.
 */
class OpsReferralLeaderboardController extends Controller
{
    public function __construct(private ReferralLeaderboardService $leaderboard) {}

    public function index(): Response
    {
        return Inertia::render('Ops/Growth/ReferralLeaderboard', [
            'leaderboard' => $this->leaderboard->topReferrers(
                limit: 50,
                anonymise: false,
            ),
        ]);
    }
}
