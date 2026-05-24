<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase-101 OWNER-FOUNDATION: a property owner — the party a property manager (the
 * landlord) manages properties on behalf of. A landlord-scoped CONTACT (name/email),
 * not a login user; user_id is reserved for a later owner-portal phase.
 */
class PropertyOwner extends Model
{
    use Auditable, HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'user_id',
        'name',
        'email',
        'phone',
        'id_number',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /** The properties this owner holds (managed by the landlord). */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'property_owner_id');
    }

    /**
     * @param  Builder<PropertyOwner>  $query
     * @return Builder<PropertyOwner>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
