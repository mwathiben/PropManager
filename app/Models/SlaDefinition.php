<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-49 SLA-PER-CATEGORY-1: per-(landlord, category, subcategory, priority)
 * SLA override row. NULL on any column means "matches anything" for that
 * dimension; landlord_id NULL means platform-default.
 *
 * No TenantScope — the cascade explicitly mixes landlord rows with global
 * rows. SlaDefinitionService is the canonical read path.
 *
 * @property int $id
 * @property int|null $landlord_id
 * @property string|null $category
 * @property string|null $subcategory
 * @property string|null $priority
 * @property int $response_seconds
 * @property int $resolution_seconds
 * @property bool $is_active
 */
class SlaDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'landlord_id',
        'category',
        'subcategory',
        'priority',
        'response_seconds',
        'resolution_seconds',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
