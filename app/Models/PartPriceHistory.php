<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-75 PARTS-PRICING-1: append-only unit-cost history for a part.
 */
class PartPriceHistory extends Model
{
    use TenantScope;

    protected $table = 'part_price_history';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PURCHASE_ORDER = 'purchase_order';

    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'part_id',
        'landlord_id',
        'cost_per_unit_cents',
        'source',
        'effective_at',
        'recorded_by',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
