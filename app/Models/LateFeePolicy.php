<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LateFeePolicy extends Model
{
    use Auditable, TenantScope;

    protected $fillable = [
        'landlord_id',
        'property_id',
        'building_id',
        'name',
        'grace_period_days',
        'fee_type',
        'fee_percentage',
        'fee_amount',
        'is_compounding',
        'compounding_frequency',
        'max_fee_cap',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'grace_period_days' => 'integer',
        'fee_percentage' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'max_fee_cap' => 'decimal:2',
        'is_compounding' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function lateFees(): HasMany
    {
        return $this->hasMany(LateFee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBuilding($query, int $buildingId)
    {
        return $query->where('building_id', $buildingId);
    }

    public function scopeForProperty($query, int $propertyId)
    {
        return $query->where('property_id', $propertyId)->whereNull('building_id');
    }

    public function scopeLandlordDefault($query)
    {
        return $query->whereNull('property_id')->whereNull('building_id');
    }

    public function calculateFee(float $baseAmount, float $existingLateFees = 0): float
    {
        $amountToCalculateOn = $this->is_compounding
            ? ($baseAmount + $existingLateFees)
            : $baseAmount;

        $fee = $this->fee_type === 'percentage'
            ? $amountToCalculateOn * ($this->fee_percentage / 100)
            : (float) $this->fee_amount;

        if ($this->max_fee_cap !== null) {
            $totalAfterFee = $existingLateFees + $fee;
            if ($totalAfterFee > $this->max_fee_cap) {
                $fee = max(0, $this->max_fee_cap - $existingLateFees);
            }
        }

        return round($fee, 2);
    }

    public function getScopeLabel(): string
    {
        if ($this->building_id) {
            return 'Building: '.($this->building?->name ?? 'Unknown');
        }
        if ($this->property_id) {
            return 'Property: '.($this->property?->name ?? 'Unknown');
        }

        return 'Default (All Properties)';
    }

    public function getFeeDescription(): string
    {
        if ($this->fee_type === 'percentage') {
            return number_format($this->fee_percentage, 1).'%';
        }

        return 'Ksh '.number_format($this->fee_amount, 2);
    }
}
