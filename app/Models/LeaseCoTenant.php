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
 * Phase-83 CO-TENANT-1: an additional tenant on a joint tenancy.
 */
class LeaseCoTenant extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'user_id',
        'name',
        'email',
        'phone',
        'national_id',
        'relationship',
        'is_responsible_for_rent',
        'liability_share',
        'removed_at',
    ];

    protected $casts = [
        'is_responsible_for_rent' => 'boolean',
        'liability_share' => 'decimal:2',
        'removed_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<LeaseCoTenant>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('removed_at');
    }
}
