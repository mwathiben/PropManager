<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-78 AMENITY-DEPTH-1: per-(building, amenity) operational detail. Written
 * exclusively by AmenityDetailService (allow-list gated). Per building — wings
 * keep their own rows (a wing may have different parking counts).
 *
 * @property int $building_id
 * @property int $landlord_id
 * @property string $amenity_key
 * @property int|null $quantity
 * @property string|null $provider
 * @property string|null $account_ref
 * @property string|null $monthly_cost
 */
class BuildingAmenityDetail extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'building_id',
        'landlord_id',
        'amenity_key',
        'quantity',
        'provider',
        'account_ref',
        'monthly_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'monthly_cost' => 'decimal:2',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
