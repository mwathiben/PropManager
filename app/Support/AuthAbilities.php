<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Phase-20 AUTHZ-FRONT-1: compute the abilities map shared via Inertia
 * to the frontend so Vue templates can gate buttons via v-can without
 * replicating role logic client-side.
 *
 * The map is a flat ['ability_name' => bool] — one entry per registered
 * Gate ability that's relevant to the UI. Per-record abilities (e.g.
 * 'update' on a specific Invoice) are NOT included here; they're
 * computed at the resource-controller level and merged into the
 * individual props payload (AUTHZ-FRONT-5).
 *
 * Cheap to compute: ~10 Gate::forUser->allows() calls per Inertia
 * response. Adding/removing abilities here changes the share payload
 * shape — keep in sync with resources/js/composables/useAuth.ts and
 * docs/runbooks/frontend-authz-and-ux.md.
 */
class AuthAbilities
{
    /**
     * @return array<string, bool>
     */
    public static function for(User $user): array
    {
        $abilities = [
            'access-admin',
            'view-audit-logs',
            'view-security-logs',
            'manage-subscription',
            'export-data',
            'request-deletion',
            'integration:webhook',
        ];

        $map = [];
        foreach ($abilities as $ability) {
            $map[$ability] = Gate::forUser($user)->allows($ability);
        }

        return $map;
    }
}
