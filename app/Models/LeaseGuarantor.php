<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-83 GUARANTOR-1: a party guaranteeing a lease.
 */
class LeaseGuarantor extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'name',
        'email',
        'phone',
        'national_id',
        'relationship',
        'guaranteed_amount',
        'status',
        'released_at',
        'released_reason',
    ];

    protected $casts = [
        'guaranteed_amount' => 'decimal:2',
        'released_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * @param  Builder<LeaseGuarantor>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param  Builder<LeaseGuarantor>  $query
     */
    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RELEASED);
    }
}
