<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $landlord_id
 * @property int|null $building_id
 * @property string $name
 * @property string|null $description
 * @property float $default_amount
 * @property bool $always_apply
 * @property bool $is_active
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User|null $landlord
 * @property-read Building|null $building
 * @property-read \Illuminate\Database\Eloquent\Collection<MoveOutDeduction> $deductions
 */
class MoveOutDeductionCategory extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'building_id',
        'name',
        'description',
        'default_amount',
        'always_apply',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'always_apply' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(MoveOutDeduction::class, 'category_id');
    }

    /**
     * Scope to filter only active categories.
     *
     * @param  Builder<MoveOutDeductionCategory>  $query
     * @return Builder<MoveOutDeductionCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter categories that should always be applied.
     *
     * @param  Builder<MoveOutDeductionCategory>  $query
     * @return Builder<MoveOutDeductionCategory>
     */
    public function scopeAlwaysApply(Builder $query): Builder
    {
        return $query->where('always_apply', true);
    }

    /**
     * Scope to filter platform-wide global defaults (null landlord_id and building_id).
     *
     * @param  Builder<MoveOutDeductionCategory>  $query
     * @return Builder<MoveOutDeductionCategory>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('landlord_id')->whereNull('building_id');
    }

    /**
     * Scope to get categories for a specific building.
     * Includes building-specific categories and landlord-level defaults (building_id is null).
     *
     * @param  Builder<MoveOutDeductionCategory>  $query
     * @return Builder<MoveOutDeductionCategory>
     */
    public function scopeForBuilding(Builder $query, ?int $buildingId): Builder
    {
        return $query->where(function ($q) use ($buildingId) {
            $q->where('building_id', $buildingId)
                ->orWhereNull('building_id');
        });
    }

    /**
     * Scope to order by sort_order and then by name.
     *
     * @param  Builder<MoveOutDeductionCategory>  $query
     * @return Builder<MoveOutDeductionCategory>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if this is a platform-wide default category.
     */
    public function isGlobal(): bool
    {
        return $this->landlord_id === null && $this->building_id === null;
    }

    /**
     * Check if this is a building-specific category.
     */
    public function isBuildingSpecific(): bool
    {
        return $this->building_id !== null;
    }
}
