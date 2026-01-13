<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveOutDeduction extends Model
{
    protected $fillable = [
        'move_out_id',
        'description',
        'amount',
        'notes',
        'photo_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the move-out this deduction belongs to
     */
    public function moveOut(): BelongsTo
    {
        return $this->belongsTo(MoveOut::class);
    }

    /**
     * Check if this deduction has a photo
     */
    public function hasPhoto(): bool
    {
        return ! empty($this->photo_path);
    }
}
