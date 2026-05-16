<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\MrrSnapshot;
use App\Models\Subscription;
use App\Services\Insight\InsightDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-36 INSIGHT-API-3: aggregate summary endpoint.
 *
 * One round-trip with engagement + usage + referrals + MRR
 * contribution. Cached 5 min per landlord — power users polling
 * this should not hammer the DB.
 */
class InsightSummaryController extends Controller
{
    public function __construct(
        private readonly InsightDashboardService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $landlordId = $user->role === 'landlord' ? (int) $user->id : (int) $user->landlord_id;

        $payload = Cache::remember(
            "insight:summary:{$landlordId}",
            now()->addMinutes(5),
            function () use ($landlordId) {
                $summary = $this->service->landlordSummary($landlordId);

                return [
                    'engagement' => [
                        'current_score' => $summary['engagement_score'],
                        'delta_7d' => $summary['engagement_score_delta_7d'],
                        'components' => $summary['engagement_components'],
                    ],
                    'usage' => [
                        'features' => $summary['usage_ratios'],
                    ],
                    'referrals' => [
                        'count_30d' => $summary['referral_count_30d'],
                    ],
                    'mrr_contribution' => [
                        'current_period_kes' => $this->landlordMrrContribution($landlordId),
                    ],
                ];
            },
        );

        return response()->json($payload);
    }

    private function landlordMrrContribution(int $landlordId): float
    {
        $sub = Subscription::query()
            ->where('user_id', $landlordId)
            ->whereNull('cancelled_at')
            ->with('plan:id,price_monthly,price_yearly')
            ->first();
        if (! $sub) {
            return 0.0;
        }

        if ($sub->billing_cycle === 'yearly') {
            return round((float) ($sub->plan?->price_yearly ?? 0) / 12.0, 2);
        }

        return round((float) ($sub->plan?->price_monthly ?? 0), 2);
    }
}
