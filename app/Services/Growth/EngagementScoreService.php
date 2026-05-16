<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\LandlordEngagementScore;
use App\Models\LandlordUsageMetric;
use App\Models\OnboardingMilestone;
use App\Models\Property;
use App\Models\SecurityLog;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Phase-34 GROWTH-ENGAGEMENT-1: composite engagement score 0-100.
 *
 * Weighted formula (PRD GROWTH-ENGAGEMENT-1, adjusted for what
 * the codebase actually has):
 *
 *   30%  recent_login         (SecurityLog EVENT_LOGIN within Nd)
 *   25%  milestone_completion (count of 6 OnboardingMilestone funnel)
 *   20%  usage_recency        (LandlordUsageMetric within 7d)
 *   15%  property_growth      (count today vs count 30d ago)
 *   10%  tenant_activity      (TenantActivity within 7d on any tenant
 *                              under this landlord)
 *
 * All five inputs use existing tables — no new schema beyond the
 * engagement_scores roll-up table itself.
 */
class EngagementScoreService
{
    public const WEIGHT_LOGIN = 0.30;
    public const WEIGHT_MILESTONES = 0.25;
    public const WEIGHT_USAGE = 0.20;
    public const WEIGHT_PROPERTY = 0.15;
    public const WEIGHT_TENANT = 0.10;

    public function compute(int $landlordId, ?DateTimeInterface $day = null): array
    {
        $day = $day ? Carbon::instance(Carbon::parse($day->format('Y-m-d'))) : Carbon::today();

        $loginScore = $this->scoreLoginRecency($landlordId, $day);
        $milestoneScore = $this->scoreMilestoneCompletion($landlordId, $day);
        $usageScore = $this->scoreUsageRecency($landlordId, $day);
        $propertyScore = $this->scorePropertyGrowth($landlordId, $day);
        $tenantScore = $this->scoreTenantActivity($landlordId, $day);

        $composite = (int) round(
            $loginScore * self::WEIGHT_LOGIN
            + $milestoneScore * self::WEIGHT_MILESTONES
            + $usageScore * self::WEIGHT_USAGE
            + $propertyScore * self::WEIGHT_PROPERTY
            + $tenantScore * self::WEIGHT_TENANT
        );

        return [
            'score' => max(0, min(100, $composite)),
            'components' => [
                'login' => $loginScore,
                'milestones' => $milestoneScore,
                'usage' => $usageScore,
                'property' => $propertyScore,
                'tenant' => $tenantScore,
            ],
        ];
    }

    public function snapshot(int $landlordId, ?DateTimeInterface $day = null): LandlordEngagementScore
    {
        $day = $day ? Carbon::instance(Carbon::parse($day->format('Y-m-d'))) : Carbon::today();
        $computed = $this->compute($landlordId, $day);

        return LandlordEngagementScore::query()->withoutGlobalScopes()->updateOrCreate(
            ['landlord_id' => $landlordId, 'day' => $day->toDateString()],
            ['score' => $computed['score'], 'components' => $computed['components']],
        );
    }

    private function scoreLoginRecency(int $landlordId, Carbon $day): int
    {
        $latest = SecurityLog::query()
            ->where('user_id', $landlordId)
            ->where('event_type', SecurityLog::EVENT_LOGIN)
            ->where('created_at', '<=', $day->copy()->endOfDay())
            ->latest('created_at')
            ->first();
        if (! $latest) {
            return 0;
        }
        $daysAgo = $latest->created_at->diffInDays($day);

        return match (true) {
            $daysAgo <= 7 => 100,
            $daysAgo <= 14 => 70,
            $daysAgo <= 30 => 30,
            default => 0,
        };
    }

    private function scoreMilestoneCompletion(int $landlordId, Carbon $day): int
    {
        $completed = OnboardingMilestone::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->whereIn('milestone', OnboardingMilestone::FUNNEL)
            ->where('created_at', '<=', $day->copy()->endOfDay())
            ->count();

        $total = count(OnboardingMilestone::FUNNEL);

        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }

    private function scoreUsageRecency(int $landlordId, Carbon $day): int
    {
        $since = $day->copy()->subDays(7)->toDateString();
        $exists = LandlordUsageMetric::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('day', '>=', $since)
            ->exists();

        return $exists ? 100 : 0;
    }

    private function scorePropertyGrowth(int $landlordId, Carbon $day): int
    {
        $today = Property::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('created_at', '<=', $day->copy()->endOfDay())
            ->count();
        $thirtyDaysAgo = Property::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('created_at', '<=', $day->copy()->subDays(30)->endOfDay())
            ->count();

        if ($today === 0) {
            return 0;
        }
        if ($today > $thirtyDaysAgo) {
            return 100;
        }
        if ($today === $thirtyDaysAgo && $today > 0) {
            return 50;
        }

        return 25;
    }

    private function scoreTenantActivity(int $landlordId, Carbon $day): int
    {
        $since = $day->copy()->subDays(7);

        // TenantActivity is keyed on tenant_id (User), where the tenant
        // belongs to this landlord via landlord_id.
        $tenantIds = User::query()
            ->where('landlord_id', $landlordId)
            ->where('role', 'tenant')
            ->pluck('id')
            ->all();
        if ($tenantIds === []) {
            return 0;
        }

        $exists = \App\Models\TenantActivity::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('created_at', '>=', $since)
            ->exists();

        return $exists ? 100 : 0;
    }
}
