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
     * When true, the creating() hook will NOT overwrite an explicitly-set
     * landlord_id on this model class. Trait statics are per-using-class, so
     * this flag is scoped to the model it's toggled on. Always flipped via
     * withoutLandlordOverride(), which restores the previous value in a
     * finally block.
     */
    protected static bool $landlordOverrideDisabled = false;

    /**
     * Run $callback with the always-overwrite landlord_id guard disabled, for
     * the rare legitimate cross-landlord server write — e.g. recording an
     * onboarding milestone for landlord A while a tenant of A (or no one) is
     * authenticated. The guard is re-armed even if $callback throws.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutLandlordOverride(callable $callback): mixed
    {
        $previous = static::$landlordOverrideDisabled;
        static::$landlordOverrideDisabled = true;

        try {
            return $callback();
        } finally {
            static::$landlordOverrideDisabled = $previous;
        }
    }

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

                // landlord (self-manager) and manager (firm/individual) are both
                // scope owners — their tenant data is keyed on their own id. A
                // manager additionally keeps landlord_id == its own id (the User
                // invariant), so every `isLandlord() ? id : landlord_id` scope
                // resolution across the app lands on the same id without a sweep.
                if (in_array($user->role, ['landlord', 'manager'], true)) {
                    $builder->where('landlord_id', $user->id);

                    return;
                }

                // Caretaker (manages a landlord's properties), tenant,
                // water_client (Phase-94) and owner (Phase-102) are all scoped
                // to their supplier landlord_id. Per-row filtering (only THEIR
                // unit / account / properties) is enforced explicitly in the
                // respective controllers — TenantScope alone is not enough for
                // these roles.
                if (in_array($user->role, ['caretaker', 'tenant', 'water_client', 'owner'], true)) {
                    // Fail CLOSED: a non-landlord user whose landlord_id is null
                    // (a malformed assignment that never set it) must be scoped
                    // to NOTHING. Without this guard the clause degrades to
                    // `where landlord_id IS NULL` and would leak every orphaned
                    // (null-landlord) row. Verified no such users exist in
                    // production — this is defense-in-depth (audit S1).
                    if (empty($user->landlord_id)) {
                        $builder->whereRaw('1 = 0');

                        return;
                    }

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
            // Defense-in-depth (audit M1 / MASS-4..6): landlord_id sits in
            // $fillable on most tenant-scoped models, so a mass-assignment
            // vector could otherwise smuggle a foreign landlord_id into
            // ::create(). ALWAYS overwrite it with the authenticated user's
            // landlord context so an attacker-supplied value can never win.
            //
            // Three carve-outs leave an explicit landlord_id intact:
            //   - no authenticated user (queue jobs, console commands, the
            //     registration flow) — trusted server code owns the value;
            //   - super admins — trusted cross-tenant writers, mirroring the
            //     bootTenantScope() global-scope bypass above;
            //   - withoutLandlordOverride() — explicit opt-out for the rare
            //     legitimate cross-landlord write (OnboardingMilestoneRecorder).
            if (! Auth::check() || static::$landlordOverrideDisabled) {
                return;
            }

            $user = Auth::user();

            if ($user->isSuperAdmin()) {
                return;
            }

            // Scope owners (landlord self-manager, manager firm/individual) own
            // their own data; attached roles (caretaker, etc.) inherit their
            // managing account's id.
            $model->landlord_id = in_array($user->role, ['landlord', 'manager'], true)
                ? $user->id
                : $user->landlord_id;
        });
    }
}
