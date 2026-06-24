<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveOutInspectionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'move_out_id',
        'inspection_item_id',
        'result',
        'notes',
    ];

    /**
     * Get the move-out this result belongs to
     */
    public function moveOut(): BelongsTo
    {
        return $this->belongsTo(MoveOut::class);
    }

    /**
     * Get the inspection item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MoveOutInspectionItem::class, 'inspection_item_id');
    }

    /**
     * Check if this item passed
     */
    public function passed(): bool
    {
        return $this->result === 'pass';
    }

    /**
     * Check if this item failed
     */
    public function failed(): bool
    {
        return $this->result === 'fail';
    }

    /**
     * Scope: Failed items
     */
    public function scopeFailed($query)
    {
        return $query->where('result', 'fail');
    }

    /**
     * Scope: Passed items
     */
    public function scopePassed($query)
    {
        return $query->where('result', 'pass');
    }
}
