<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LateFeePolicy extends Model
{
    use Auditable, HasFactory, TenantScope;

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
        // Phase-17 MONEY-2: legacy float entry point — delegates to the
        // Money-backed implementation. Retained for backwards compat
        // with existing tests + callers that haven't migrated yet.
        return $this->calculateFeeMoney(
            Money::fromString((string) $baseAmount),
            Money::fromString((string) $existingLateFees),
        )->toFloatLossy();
    }

    /**
     * Phase-17 MONEY-2: canonical Money-backed late-fee calculation.
     * Uses bcmath for the percentage / cap arithmetic so 12-month
     * compounding against a known closed-form matches at scale=2.
     * MONEY-4 (Phase 3): percentage rounding is banker's half-even
     * via Money::multiply (which roundHalfEvens internally).
     */
    public function calculateFeeMoney(Money $baseAmount, Money $existingLateFees): Money
    {
        $amountToCalculateOn = $this->is_compounding
            ? $baseAmount->add($existingLateFees)
            : $baseAmount;

        $fee = $this->fee_type === 'percentage'
            ? $amountToCalculateOn->multiply(bcdiv((string) $this->fee_percentage, '100', 6))
            : Money::fromString((string) $this->fee_amount);

        if ($this->max_fee_cap !== null) {
            $cap = Money::fromString((string) $this->max_fee_cap);
            $totalAfterFee = $existingLateFees->add($fee);

            if ($totalAfterFee->greaterThan($cap)) {
                $fee = $cap->subtract($existingLateFees)->clampPositive();
            }
        }

        return $fee;
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
