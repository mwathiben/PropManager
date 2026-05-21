<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-75 PARTS-PRICING-2: a supplier (vendor) entry for a part — unit cost,
 * lead time, min order qty.
 */
class PartSupplier extends Model
{
    use TenantScope;

    protected $fillable = [
        'part_id',
        'vendor_id',
        'landlord_id',
        'unit_cost_cents',
        'lead_time_days',
        'min_order_qty',
    ];

    protected $casts = [
        'unit_cost_cents' => 'integer',
        'lead_time_days' => 'integer',
        'min_order_qty' => 'integer',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
