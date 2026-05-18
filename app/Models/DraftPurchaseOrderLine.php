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
    protected $fillable = [
        'draft_purchase_order_id',
        'part_id',
        'qty_suggested',
        'cost_per_unit_cents_snapshot',
    ];

    protected $casts = [
        'qty_suggested' => 'integer',
        'cost_per_unit_cents_snapshot' => 'integer',
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
