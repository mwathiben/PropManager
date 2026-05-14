<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Phase-20 AUTHZ-FRONT-1: user-level abilities map shared via Inertia
 * so Vue templates can gate buttons via useAuth().can() without
 * replicating role logic client-side.
 *
 * Phase-21 DEFER-AUTHZ-3: per-record abilities resolved via forRecord()
 * and merged into the resource payload by show-controllers (e.g.
 * Invoices/Show, Tenants/Show). Vue templates consume them as
 * props.invoice.abilities.update — the Policy outcome for THIS record,
 * not just the user-level Gate registry.
 *
 * Keep in sync with resources/js/composables/useAuth.ts and
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
            // Phase-21 DEFER-AUTHZ-1: management abilities exposed for
            // Vue index-page action gating (Add / Delete / Bulk). Mirror
            // the Gate definitions in AuthServiceProvider::boot. DPA-4
            // restriction propagates via Gate::before so a restricted
            // landlord/caretaker sees these as false.
            'tenants:manage',
            'invoices:manage',
            'payments:manage',
            'properties:manage',
            'buildings:manage',
            'units:manage',
            'documents:manage',
            'settings:manage',
            'team:manage',
            'templates:manage',
            'finances:manage',
            'imports:manage',
        ];

        $map = [];
        foreach ($abilities as $ability) {
            $map[$ability] = Gate::forUser($user)->allows($ability);
        }

        return $map;
    }

    /**
     * Phase-21 DEFER-AUTHZ-3: resolve per-record abilities by calling
     * Gate::forUser($user)->allows($ability, $record) for each ability.
     *
     * Each ability must correspond to a Policy method on the model's
     * registered policy (e.g. InvoicePolicy::update for ability 'update').
     * Returns flat ['ability' => bool] — Vue templates consume as
     * props.<resource>.abilities.<ability>.
     *
     * @param  array<int, string>  $abilities
     * @return array<string, bool>
     */
    public static function forRecord(User $user, Model $record, array $abilities): array
    {
        $map = [];
        foreach ($abilities as $ability) {
            $map[$ability] = Gate::forUser($user)->allows($ability, $record);
        }

        return $map;
    }
}
