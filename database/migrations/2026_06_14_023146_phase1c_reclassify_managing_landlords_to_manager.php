<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 1c: existing `landlord` accounts that manage properties on owners'
     * behalf — evidenced by PropertyOwner links (property_owners.landlord_id
     * points at them) — are really management firms/individuals. Reclassify
     * them to `manager`, pinning landlord_id == own id (the scope-owner
     * invariant; a raw update bypasses the User saved-hook, so set it here).
     *
     * Behaviour-preserving: a manager scopes exactly like the landlord it was
     * (TenantScope keys both on the user's own id) and Phase 1b granted managers
     * the same operational access. Only the role label and the (now explicitly
     * self) landlord_id change. The subquery reads property_owners, never users,
     * so there is no "update target in FROM" conflict.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('users')
                ->where('role', 'landlord')
                ->whereIn('id', function ($q) {
                    $q->select('landlord_id')->distinct()->from('property_owners');
                })
                ->update(['role' => 'manager', 'landlord_id' => DB::raw('id')]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('users')
                ->where('role', 'manager')
                ->whereIn('id', function ($q) {
                    $q->select('landlord_id')->distinct()->from('property_owners');
                })
                ->update(['role' => 'landlord']);
        });
    }
};
