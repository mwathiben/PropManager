<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $landlord_id
 * @property int|null $building_id
 * @property string $requirement_type
 * @property string $label
 * @property string|null $description
 * @property bool $is_required
 * @property bool $is_active
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read User|null $landlord
 * @property-read Building|null $building
 * @property-read \Illuminate\Database\Eloquent\Collection<TenantKycSubmission> $submissions
 */
class KycRequirement extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'landlord_id',
        'building_id',
        'requirement_type',
        'label',
        'description',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(TenantKycSubmission::class, 'requirement_id');
    }

    /**
     * Scope to filter only active requirements.
     *
     * @param  Builder<KycRequirement>  $query
     * @return Builder<KycRequirement>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter only required requirements.
     *
     * @param  Builder<KycRequirement>  $query
     * @return Builder<KycRequirement>
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to filter platform-wide global defaults (null landlord_id and building_id).
     *
     * @param  Builder<KycRequirement>  $query
     * @return Builder<KycRequirement>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('landlord_id')->whereNull('building_id');
    }

    /**
     * Scope to get requirements for a specific building.
     * Includes building-specific requirements and landlord-level defaults (building_id is null).
     *
     * @param  Builder<KycRequirement>  $query
     * @return Builder<KycRequirement>
     */
    public function scopeForBuilding(Builder $query, ?int $buildingId): Builder
    {
        return $query->where(function ($q) use ($buildingId) {
            $q->where('building_id', $buildingId)
                ->orWhereNull('building_id');
        });
    }

    /**
     * Check if this is a platform-wide default requirement.
     */
    public function isGlobal(): bool
    {
        return $this->landlord_id === null && $this->building_id === null;
    }

    /**
     * Check if this is a building-specific requirement.
     */
    public function isBuildingSpecific(): bool
    {
        return $this->building_id !== null;
    }

    /**
     * Scope to order by sort_order and then by label.
     *
     * @param  Builder<KycRequirement>  $query
     * @return Builder<KycRequirement>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }
}
