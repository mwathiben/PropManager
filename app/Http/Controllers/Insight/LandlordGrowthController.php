<?php

declare(strict_types=1);

namespace App\Http\Controllers\Insight;

use App\Http\Controllers\Controller;
use App\Models\LandlordEngagementScore;
use App\Models\Referral;
use App\Services\Insight\InsightDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-36 INSIGHT-LANDLORD-3: landlord-facing /growth deep-dive.
 *
 * Dashboard widgets surface the headline KPIs; this page renders
 * the 90-day trend tables for landlords who want more than a
 * snapshot. Three tables: engagement trend, referral ledger,
 * usage history.
 */
class LandlordGrowthController extends Controller
{
    public function __construct(
        private readonly InsightDashboardService $service,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $engagement = LandlordEngagementScore::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('day', '>=', now()->subDays(90)->toDateString())
            ->orderBy('day', 'desc')
            ->get(['day', 'score', 'components']);

        $referrals = Referral::query()
            ->where('referrer_user_id', $landlordId)
            ->latest()
            ->limit(50)
            ->get(['id', 'referred_user_id', 'status', 'attributed_at', 'created_at']);

        return Inertia::render('Insight/LandlordGrowth', [
            'engagement_history' => $engagement,
            'referrals' => $referrals,
            'summary' => $this->service->landlordSummary((int) $landlordId),
        ]);
    }
}
