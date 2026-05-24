<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-103 OWNER-PAYOUTS: a disbursement the property manager has made to a property owner.
 * The owner's balance is derived (statement net − non-voided payouts); these are the money
 * movements. Landlord-scoped (TenantScope); voided, never hard-deleted.
 */
class OwnerPayout extends Model
{
    use Auditable, HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'property_owner_id',
        'amount',
        'currency',
        'paid_on',
        'method',
        'reference',
        'notes',
        'voided_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => Currency::class,
        'paid_on' => 'date',
        'voided_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'property_owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Live (non-voided) payouts — the only ones that count toward the owner's balance.
     *
     * @param  Builder<OwnerPayout>  $query
     * @return Builder<OwnerPayout>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
