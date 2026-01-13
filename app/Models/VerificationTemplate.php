<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerificationTemplate extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'property_id',
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the landlord who owns this template
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the property this template is for (optional)
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get all items in this template
     */
    public function items(): HasMany
    {
        return $this->hasMany(VerificationItem::class)->orderBy('sort_order');
    }

    /**
     * Get required items only
     */
    public function requiredItems(): HasMany
    {
        return $this->hasMany(VerificationItem::class)->where('is_required', true)->orderBy('sort_order');
    }

    /**
     * Scope: Get default template
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: Get templates for a specific property
     */
    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }
}
