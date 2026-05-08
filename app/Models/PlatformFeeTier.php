<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeeTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_volume',
        'max_volume',
        'fee_percentage',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_volume' => 'decimal:2',
        'max_volume' => 'decimal:2',
        'fee_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function forVolume(float $volume): ?self
    {
        return static::active()
            ->where('min_volume', '<=', $volume)
            ->where(function (Builder $query) use ($volume) {
                $query->whereNull('max_volume')
                    ->orWhere('max_volume', '>=', $volume);
            })
            ->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
