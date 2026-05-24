<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'landlord_id',
        // Phase-101 OWNER-FOUNDATION: the owner this property is managed for (nullable).
        'property_owner_id',
        'name',
        'type',
        'address',
        // Phase-27 BI-NOI-2: cap-rate denominator. Nullable; pages
        // render N/A when absent.
        'estimated_value',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
    ];

    // --- RELATIONSHIPS ---

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }

    /**
     * Phase-27 BI-NOI-1: convenience join — every unit under this
     * property via the buildings table. Used by NoiService per-property
     * unit-count aggregation.
     */
    public function units()
    {
        return $this->hasManyThrough(Unit::class, Building::class);
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /** Phase-101: the owner this property is managed on behalf of (nullable). */
    public function owner()
    {
        return $this->belongsTo(PropertyOwner::class, 'property_owner_id');
    }
}
