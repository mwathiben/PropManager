<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\Building;
use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-79 WATER-GATE: the water module is a CONDITIONAL feature. It is visible
 * to a landlord (and their caretakers/tenants) only when BOTH hold:
 *   1. the subscription plan permits water billing (the ceiling), AND
 *   2. the landlord actually charges tenants for water — a water_billing_type of
 *      consumption|flat_rate on the global PaymentConfiguration OR on any of the
 *      landlord's buildings.
 *
 * This replaces the old plan-only gate so a landlord who picked "No Water
 * Billing" in onboarding never sees the water hub, and one who does charge sees
 * it. Cached 300s per landlord; write paths that change water config bust the key.
 */
class WaterModuleAccess
{
    private const CHARGING_TYPES = ['consumption', 'flat_rate'];

    public static function enabledFor(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $landlordId = $user->isScopeOwner() ? (int) $user->id : (int) ($user->landlord_id ?? 0);
        if ($landlordId === 0) {
            return false;
        }

        return self::enabledForLandlord($landlordId, $user->isScopeOwner() ? $user : $user->landlord);
    }

    public static function enabledForLandlord(int $landlordId, ?User $landlord = null): bool
    {
        return Cache::remember("phase79:water-module:{$landlordId}", 300, function () use ($landlordId, $landlord) {
            $planAllows = $landlord !== null
                ? $landlord->canAccessFeature('water_billing')
                : (User::find($landlordId)?->canAccessFeature('water_billing') ?? false);

            if (! $planAllows) {
                return false;
            }

            return self::chargesForWater($landlordId);
        });
    }

    public static function forget(int $landlordId): void
    {
        Cache::forget("phase79:water-module:{$landlordId}");
    }

    private static function chargesForWater(int $landlordId): bool
    {
        $configCharges = PaymentConfiguration::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('water_billing_type', self::CHARGING_TYPES)
            ->exists();

        if ($configCharges) {
            return true;
        }

        return Building::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('water_billing_type', self::CHARGING_TYPES)
            ->exists();
    }
}
