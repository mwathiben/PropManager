<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\ProductEvent;
use App\Models\User;

/**
 * Phase-35 PLATFORM-ANALYTICS-1: thin tracker facade.
 *
 *   - track($name, $properties, $user) writes a single append-only
 *     product_events row.
 *   - Fail-open: analytics MUST NEVER block a request.
 *   - landlord_id auto-resolves: for landlords it is $user->id; for
 *     tenants it is $user->landlord_id; for guests it is null.
 */
class ProductEventTracker
{
    public function track(string $eventName, array $properties = [], ?User $user = null): void
    {
        try {
            $landlordId = null;
            if ($user !== null) {
                $landlordId = $user->effectiveScopeIdOrNull();
            }

            ProductEvent::query()->withoutGlobalScopes()->create([
                'user_id' => $user?->id,
                'landlord_id' => $landlordId,
                'event_name' => $eventName,
                'properties' => $properties === [] ? null : $properties,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Fail-open: analytics MUST NEVER throw out of the request path.
        }
    }
}
