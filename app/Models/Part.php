<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-49 PARTS-INVENTORY-1: per-landlord parts catalogue.
 *
 * @property int $id
 * @property int $landlord_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $category
 * @property int $cost_per_unit_cents
 * @property int $qty_available
 * @property int $reorder_threshold
 * @property bool $is_active
 */
class Part extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'landlord_id',
        'name',
        'sku',
        'category',
        'cost_per_unit_cents',
        'qty_available',
        'reorder_threshold',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_parts')
            ->withPivot(['qty_used', 'cost_allocated_cents', 'recorded_by', 'recorded_at']);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PartPriceHistory::class)->orderByDesc('effective_at');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(PartSupplier::class);
    }

    /**
     * Phase-75 PARTS-PRICING-2: cheapest supplier by unit cost (null if none).
     */
    public function cheapestSupplier(): ?PartSupplier
    {
        return $this->suppliers()->orderBy('unit_cost_cents')->first();
    }

    /**
     * Fastest supplier by lead time (null if none).
     */
    public function fastestSupplier(): ?PartSupplier
    {
        return $this->suppliers()->orderBy('lead_time_days')->first();
    }

    public function scopeBelowThreshold(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereColumn('qty_available', '<=', 'reorder_threshold');
    }

    public function isBelowThreshold(): bool
    {
        return $this->is_active && $this->qty_available <= $this->reorder_threshold;
    }
}
