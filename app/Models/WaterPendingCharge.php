<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-90: an ad-hoc water charge (e.g. a reconnection fee) waiting to be folded
 * into a lease's next invoice. applied_at/applied_invoice_id stamp it billed.
 */
class WaterPendingCharge extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'lease_id',
        'amount',
        'type',
        'description',
        'applied_invoice_id',
        'applied_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<WaterPendingCharge>  $query
     * @return \Illuminate\Database\Eloquent\Builder<WaterPendingCharge>
     */
    public function scopeUnapplied($query)
    {
        return $query->whereNull('applied_at');
    }
}
