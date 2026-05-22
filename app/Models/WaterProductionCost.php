<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-91: a borehole/water production-cost entry (pump electricity, maintenance,
 * permit fees). Summed over a window to compute the cost-of-production-vs-revenue
 * margin in WaterIntelligenceService. building_id null = whole-portfolio cost.
 */
class WaterProductionCost extends Model
{
    use HasFactory, TenantScope;

    /** @var list<string> */
    public const CATEGORIES = ['electricity', 'maintenance', 'permit', 'other'];

    protected $fillable = [
        'landlord_id',
        'building_id',
        'cost_date',
        'amount',
        'category',
        'note',
    ];

    protected $casts = [
        'cost_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
