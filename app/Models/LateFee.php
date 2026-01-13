<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateFee extends Model
{
    use Auditable, TenantScope;

    protected $fillable = [
        'invoice_id',
        'late_fee_policy_id',
        'landlord_id',
        'fee_amount',
        'cumulative_total',
        'applied_date',
        'days_overdue',
        'is_waived',
        'waived_by',
        'waived_at',
        'waiver_reason',
    ];

    protected $casts = [
        'fee_amount' => 'decimal:2',
        'cumulative_total' => 'decimal:2',
        'applied_date' => 'date',
        'days_overdue' => 'integer',
        'is_waived' => 'boolean',
        'waived_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LateFeePolicy::class, 'late_fee_policy_id');
    }

    public function waivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_waived', false);
    }

    public function scopeWaived($query)
    {
        return $query->where('is_waived', true);
    }

    public function waive(int $userId, string $reason): bool
    {
        $this->update([
            'is_waived' => true,
            'waived_by' => $userId,
            'waived_at' => now(),
            'waiver_reason' => $reason,
        ]);

        $this->invoice->recalculateLateFees();

        return true;
    }
}
