<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait TenantScope
{
    /**
     * The "Boot" method of the model.
     * This is where we attach the Global Scope.
     */
    protected static function bootTenantScope()
    {
        // Only apply this scope if a user is logged in and NOT a Super Admin
        if (Auth::check() && ! Auth::user()->isSuperAdmin()) {

            static::addGlobalScope('landlord', function (Builder $builder) {

                $user = Auth::user();

                if ($user->role === 'landlord') {
                    // Landlord sees their own data
                    $builder->where('landlord_id', $user->id);
                } elseif ($user->role === 'caretaker') {
                    // Caretaker sees data belonging to their assigned Landlord
                    $builder->where('landlord_id', $user->landlord_id);
                } elseif ($user->role === 'tenant') {
                    // Tenants generally only see data linked to their specific ID
                    // But for general queries, we scope to their landlord
                    $builder->where('landlord_id', $user->landlord_id);
                }
            });
        }
    }

    /**
     * Automatically fill the 'landlord_id' when creating new records.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();

                // If creator is Landlord, ID is theirs.
                // If creator is Caretaker, ID is their boss's.
                $model->landlord_id = $user->role === 'landlord' ? $user->id : $user->landlord_id;
            }
        });
    }
}
