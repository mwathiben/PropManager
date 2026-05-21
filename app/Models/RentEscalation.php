<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-83 RENT-ESCALATION-1: a scheduled future rent increase on a lease.
 * Applied by the rent:apply-escalations cron on its effective_date.
 */
class RentEscalation extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'escalation_type',
        'amount',
        'effective_date',
        'status',
        'applied_at',
        'new_rent_amount',
        'rent_history_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'new_rent_amount' => 'decimal:2',
        'effective_date' => 'date',
        'applied_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * @param  Builder<RentEscalation>  $query
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scheduled escalations whose effective date has arrived.
     *
     * @param  Builder<RentEscalation>  $query
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->whereDate('effective_date', '<=', now()->toDateString());
    }

    /**
     * The rent this escalation would produce from a given current rent.
     */
    public function computeNewRent(float $currentRent): float
    {
        $next = $this->escalation_type === self::TYPE_PERCENTAGE
            ? $currentRent * (1 + ((float) $this->amount / 100))
            : $currentRent + (float) $this->amount;

        return round($next, 2);
    }
}
