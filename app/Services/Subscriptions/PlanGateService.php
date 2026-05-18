<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-60 FEATURE-GATES-1: thin wrapper around the existing
 * User::canAccessFeature() that adds (a) Cache::remember 5m so the
 * same feature isn't re-resolved on every controller hit and
 * (b) plan_feature_denied_count{feature} gauge increment when the
 * gate denies, so ops sees the rate landlords hit plan walls.
 *
 * Source of truth for which plan exposes which feature stays on
 * User::canAccessFeature() — this service exists for the side
 * effects, not to duplicate the lookup logic.
 *
 * Feature names supported (mirror User::canAccessFeature):
 *   water_billing, ocr, reports, bulk_operations, documents, sms
 */
class PlanGateService
{
    public function __construct(
        private readonly MetricsService $metrics,
    ) {}

    public function can(string $feature, ?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null) {
            return false;
        }

        $allowed = Cache::remember(
            "phase60:plan-gate:{$user->id}:{$feature}",
            300,
            fn () => $user->canAccessFeature($feature),
        );

        if (! $allowed) {
            $this->metrics->increment('plan_feature_denied_count', 1, ['feature' => $feature]);
        }

        return $allowed;
    }

    /**
     * Return an array of feature => bool for the given user. Used by
     * the Inertia share contract so Vue components can render lock
     * icons without each controller having to assemble the bundle.
     *
     * @return array<string, bool>
     */
    public function featuresFor(?User $user = null): array
    {
        $user ??= auth()->user();
        if ($user === null) {
            return [];
        }

        $features = ['water_billing', 'ocr', 'reports', 'bulk_operations', 'documents', 'sms'];

        return array_combine(
            $features,
            array_map(fn ($f) => Cache::remember(
                "phase60:plan-gate:{$user->id}:{$f}",
                300,
                fn () => $user->canAccessFeature($f),
            ), $features),
        );
    }
}
