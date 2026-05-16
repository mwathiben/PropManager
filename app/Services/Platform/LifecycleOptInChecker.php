<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Phase-35 PLATFORM-NOTIF-1: shared gate for the 4 Phase-34
 * lifecycle Mailables. Returns true when the landlord wants to
 * receive marketing-class emails on the channel; false otherwise.
 *
 * Default opt-in (no row found = consent) since landlords are
 * paying. Opt-out is explicit via /api/notifications/preferences.
 */
class LifecycleOptInChecker
{
    public function allows(User $user, string $channel = 'email'): bool
    {
        $pref = NotificationPreference::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first();
        if (! $pref) {
            return true;
        }

        return (bool) $pref->lifecycle_enabled
            && (bool) ($pref->{$channel.'_enabled'} ?? false);
    }
}
