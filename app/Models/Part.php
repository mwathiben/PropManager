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
use Illuminate\Support\Carbon;

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

    /**
     * Phase-75 PARTS-PREDICT-2: supplier lead time used for forecasting —
     * the cheapest known supplier's lead time, else the config default.
     */
    public function leadTimeDays(): int
    {
        $supplier = $this->relationLoaded('suppliers')
            ? $this->suppliers->sortBy('unit_cost_cents')->first()
            : $this->cheapestSupplier();

        return (int) ($supplier->lead_time_days ?? config('maintenance.default_lead_time_days', 7));
    }

    /**
     * Static threshold plus the stock projected to be consumed while a
     * replacement order is in transit (ceil of lead-time * daily usage).
     */
    public function effectiveThreshold(float $dailyRate, int $leadTimeDays): int
    {
        $buffer = (int) ceil(max(0, $leadTimeDays) * max(0.0, $dailyRate));

        return $this->reorder_threshold + $buffer;
    }

    public function belowEffectiveThreshold(float $dailyRate, int $leadTimeDays): bool
    {
        return $this->is_active && $this->qty_available <= $this->effectiveThreshold($dailyRate, $leadTimeDays);
    }

    /**
     * Projected date the part hits zero at the current usage rate. Null when
     * the part is not being consumed (rate 0) — no meaningful forecast.
     */
    public function projectedStockoutDate(float $dailyRate): ?Carbon
    {
        if ($dailyRate <= 0) {
            return null;
        }

        return Carbon::today()->addDays((int) floor($this->qty_available / $dailyRate));
    }
}
