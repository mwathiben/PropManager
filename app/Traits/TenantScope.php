<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * TenantScope Trait - Multi-tenancy data isolation for landlord-owned models.
 *
 * WHY this trait exists:
 * In a multi-tenant SaaS, each landlord's data must be completely isolated.
 * Without automatic scoping, every query would need explicit where('landlord_id', $id)
 * clauses, creating maintenance burden and risk of accidental cross-tenant data leaks.
 *
 * Role-based scoping:
 * - Landlords see only their own data (landlord_id = user.id)
 * - Caretakers see their assigned landlord's data (landlord_id = user.landlord_id)
 * - Tenants see their landlord's data (landlord_id = user.landlord_id)
 *
 * WHY Super Admin bypasses scope:
 * Admin dashboards require cross-landlord visibility for reporting, debugging,
 * and system-wide statistics. Admins have no landlord_id, so the scope would
 * filter out all data. Bypass is safe because admins already have full system access.
 *
 * SECURITY: To bypass scope safely (admin only), use:
 *   Model::withoutGlobalScope('landlord')->where('landlord_id', $specificId)
 * Always re-apply explicit landlord filter to prevent accidental full-table access.
 *
 * @see AdminController for scope bypass examples
 */
trait TenantScope
{
    /**
     * Attach global landlord scope on model boot.
     * Skipped for Super Admins who need cross-tenant visibility.
     */
    protected static function bootTenantScope()
    {
        // Super Admins bypass all scoping for admin dashboard access
        if (Auth::check() && ! Auth::user()->isSuperAdmin()) {

            static::addGlobalScope('landlord', function (Builder $builder) {

                $user = Auth::user();

                if ($user->role === 'landlord') {
                    $builder->where('landlord_id', $user->id);
                } elseif ($user->role === 'caretaker') {
                    // Caretaker manages landlord's properties, sees landlord's data
                    $builder->where('landlord_id', $user->landlord_id);
                } elseif ($user->role === 'tenant') {
                    // Tenant scoped to their landlord for general queries
                    // (tenant-specific filtering done at controller level)
                    $builder->where('landlord_id', $user->landlord_id);
                }
            });
        }
    }

    /**
     * Auto-populate landlord_id on record creation.
     * Ensures data ownership is set correctly regardless of who creates the record.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();

                // Landlord owns their data; caretaker's data belongs to their boss
                $model->landlord_id = $user->role === 'landlord' ? $user->id : $user->landlord_id;
            }
        });
    }
}
