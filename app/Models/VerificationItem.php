<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerificationItem extends Model
{
    protected $fillable = [
        'verification_template_id',
        'name',
        'document_type',
        'description',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the template this item belongs to
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(VerificationTemplate::class, 'verification_template_id');
    }

    /**
     * Get all verification statuses for this item
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(TenantVerification::class);
    }

    /**
     * Scope: Required items only
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope: Order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
