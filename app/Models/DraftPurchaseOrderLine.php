<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-54 PARTS-REORDER-1: a single line inside a DraftPurchaseOrder.
 * cost_per_unit_cents_snapshot captures the Part's cost at suggestion
 * time so a later price change doesn't silently rewrite history.
 */
class DraftPurchaseOrderLine extends Model
{
    public const REASON_STATIC = 'static';

    public const REASON_LEAD_TIME = 'lead_time_buffer';

    protected $fillable = [
        'draft_purchase_order_id',
        'part_id',
        'qty_suggested',
        'cost_per_unit_cents_snapshot',
        'trigger_reason',
        'projected_stockout_at',
    ];

    protected $casts = [
        'qty_suggested' => 'integer',
        'cost_per_unit_cents_snapshot' => 'integer',
        'projected_stockout_at' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DraftPurchaseOrder::class, 'draft_purchase_order_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
