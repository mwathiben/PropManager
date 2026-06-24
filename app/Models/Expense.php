<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\EnforcesAccountingPeriodLock;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use Auditable, EnforcesAccountingPeriodLock, HasFactory, TenantScope;

    protected function accountingPeriodDateColumn(): string
    {
        return 'expense_date';
    }

    protected $fillable = [
        'landlord_id',
        'category_id',
        'vendor_id',
        'property_id',
        'building_id',
        'unit_id',
        // Phase-27 BI-NOI-3: how to attribute this expense to
        // properties when computing NOI. Defaults to 'direct' (the
        // pre-Phase-27 behaviour) so existing data is unchanged.
        'allocation_method',
        'description',
        'amount',
        'expense_date',
        'payment_method',
        'reference',
        'receipt_path',
        'notes',
        'is_recurring',
        'recurring_frequency',
    ];

    /**
     * Phase-27 BI-NOI-3: allowed allocation methods. Validation +
     * UI picker source this constant; NoiService::byProperty switches
     * on it.
     */
    public const ALLOCATION_METHODS = ['direct', 'per_unit', 'per_revenue', 'per_floor_area'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function getLocationLabel(): string
    {
        if ($this->unit_id) {
            return 'Unit: '.($this->unit?->unit_number ?? 'Unknown');
        }
        if ($this->building_id) {
            return 'Building: '.($this->building?->name ?? 'Unknown');
        }
        if ($this->property_id) {
            return 'Property: '.($this->property?->name ?? 'Unknown');
        }

        return 'General';
    }
}
