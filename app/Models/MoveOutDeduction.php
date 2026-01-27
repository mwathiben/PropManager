<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveOutDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'move_out_id',
        'category_id',
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
     * Get the category this deduction belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MoveOutDeductionCategory::class, 'category_id');
    }

    /**
     * Scope to filter deductions that have a category assigned.
     *
     * @param  Builder<MoveOutDeduction>  $query
     * @return Builder<MoveOutDeduction>
     */
    public function scopeWithCategory(Builder $query): Builder
    {
        return $query->whereNotNull('category_id');
    }

    /**
     * Scope to filter deductions without a category (legacy or custom).
     *
     * @param  Builder<MoveOutDeduction>  $query
     * @return Builder<MoveOutDeduction>
     */
    public function scopeWithoutCategory(Builder $query): Builder
    {
        return $query->whereNull('category_id');
    }

    /**
     * Check if this deduction has a photo
     */
    public function hasPhoto(): bool
    {
        return ! empty($this->photo_path);
    }
}
