<?php

declare(strict_types=1);

namespace App\Services\Insight;

use App\Models\AlertFiring;
use App\Models\LandlordEngagementScore;
use App\Models\MrrSnapshot;
use App\Models\OperationalIncident;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Growth\ChurnService;

/**
 * Phase-36 INSIGHT-OPS-2 / LANDLORD-1: composes the top-of-funnel
 * KPIs for both the super_admin operator dashboard and the
 * landlord-facing growth widgets. Single source of truth — the
 * controller just delegates here.
 */
class InsightDashboardService
{
    public function __construct(
        private readonly ChurnService $churnService,
    ) {}

    /**
     * Super-admin operator dashboard payload.
     */
    public function operatorSummary(): array
    {
        $mrrToday = $this->mrrTotalForDay(now()->toDateString());
        $mrr30dAgo = $this->mrrTotalForDay(now()->subDays(30)->toDateString());
        $mrrDeltaPct = $mrr30dAgo > 0
            ? round((($mrrToday - $mrr30dAgo) / $mrr30dAgo) * 100, 2)
            : 0.0;

        return [
            'mrr_total_kes_today' => $mrrToday,
            'mrr_delta_30d_pct' => $mrrDeltaPct,
            'monthly_churn_rate' => $this->churnService->monthlyChurnRate(),
            'active_incident_count' => OperationalIncident::query()
                ->whereIn('status', [
                    OperationalIncident::STATUS_OPEN,
                    OperationalIncident::STATUS_INVESTIGATING,
                    OperationalIncident::STATUS_MITIGATED,
                ])
                ->count(),
            'last_24h_alert_count' => AlertFiring::query()
                ->where('fired_at', '>=', now()->subDay())
                ->count(),
            'unresolved_alert_count' => AlertFiring::query()
                ->whereNull('resolved_at')
                ->count(),
        ];
    }

    /**
     * Landlord growth widget payload.
     */
    public function landlordSummary(int $landlordId): array
    {
        $latestScore = LandlordEngagementScore::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->latest('day')
            ->first();

        $sevenDaysAgoScore = LandlordEngagementScore::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('day', '<=', now()->subDays(7)->toDateString())
            ->latest('day')
            ->first();

        $delta = ($latestScore?->score ?? 0) - ($sevenDaysAgoScore?->score ?? 0);

        $referralCount = Referral::query()
            ->where('referrer_user_id', $landlordId)
            ->where('attributed_at', '>=', now()->subDays(30))
            ->count();

        $plan = Subscription::query()
            ->where('user_id', $landlordId)
            ->whereNull('cancelled_at')
            ->with('plan:id,slug,name,max_properties,max_units,max_caretakers,max_buildings')
            ->first()?->plan;

        return [
            'engagement_score' => $latestScore?->score ?? 0,
            'engagement_score_delta_7d' => $delta,
            'engagement_components' => $latestScore?->components ?? [],
            'referral_count_30d' => $referralCount,
            'current_plan_slug' => $plan?->slug,
            'usage_ratios' => $this->usageRatiosForLandlord($landlordId, $plan),
        ];
    }

    /**
     * @return array<int, array{feature: string, usage: int, limit: int, ratio: float}>
     */
    public function usageRatiosForLandlord(int $landlordId, ?SubscriptionPlan $plan): array
    {
        if (! $plan) {
            return [];
        }
        $landlord = User::find($landlordId);
        if (! $landlord) {
            return [];
        }

        $out = [];
        foreach (['properties', 'units', 'caretakers', 'buildings'] as $feature) {
            $limit = (int) $landlord->getLimit($feature);
            if ($limit <= 0) {
                continue;
            }
            $usage = (int) $landlord->getUsage($feature);
            $ratio = $limit > 0 ? round($usage / $limit, 4) : 0.0;
            $out[] = [
                'feature' => $feature,
                'usage' => $usage,
                'limit' => $limit,
                'ratio' => $ratio,
            ];
        }

        return $out;
    }

    private function mrrTotalForDay(string $day): float
    {
        return (float) MrrSnapshot::query()
            ->where('day', $day)
            ->sum('mrr_kes');
    }
}
