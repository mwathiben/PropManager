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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-94 WATER-CLIENTS-FOUNDATION: a water connection (the "water line") — the
 * analogue of a Lease for a non-tenant water client. Belongs to a landlord;
 * user_id is the water-client account once onboarded (Phase 95); links to a Meter
 * (the metering point, which may be unit-less). Carries the landlord's identifier
 * + billing_mode + the (different) client_rate the Phase-97 biller will use.
 */
class WaterConnection extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    /** @var list<string> */
    public const BILLING_MODES = ['metered', 'flat_rate'];

    protected $fillable = [
        'landlord_id',
        'user_id',
        'unit_id',
        'meter_id',
        'identifier',
        'client_name',
        'billing_mode',
        'client_rate',
        'status',
        'connected_at',
        'notes',
    ];

    protected $casts = [
        'client_rate' => 'decimal:2',
        'connected_at' => 'date',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /** The water-client account holder (null until onboarded — Phase 95). */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /** Phase-98/99: a water-client invoice is anchored to this connection. */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'water_connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @param  Builder<WaterConnection>  $query
     * @return Builder<WaterConnection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
