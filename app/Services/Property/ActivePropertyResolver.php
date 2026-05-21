<?php

declare(strict_types=1);

namespace App\Services\Property;

use App\Models\Property;
use App\Models\User;

/**
 * Phase-78 PROPERTY-SWITCH-1: resolve the landlord's effective active property —
 * the stored users.active_property_id when it still exists and is owned, else the
 * landlord's first property, else null (no properties yet).
 */
class ActivePropertyResolver
{
    public function resolve(User $user): ?Property
    {
        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        if ($user->active_property_id) {
            $stored = Property::query()
                ->where('id', $user->active_property_id)
                ->where('landlord_id', $landlordId)
                ->first();

            if ($stored !== null) {
                return $stored;
            }
        }

        return Property::query()
            ->where('landlord_id', $landlordId)
            ->orderBy('id')
            ->first();
    }
}
