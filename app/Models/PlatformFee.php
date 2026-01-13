<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformFee extends Model
{
    use TenantScope;

    protected $fillable = [
        'payment_id',
        'landlord_id',
        'payout_account_id',
        'gross_amount',
        'fee_amount',
        'net_amount',
        'fee_type',
        'fee_percentage_applied',
        'status',
        'paystack_split_reference',
        'split_details',
        'collected_at',
        'settled_at',
        'notes',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'fee_percentage_applied' => 'decimal:2',
        'split_details' => 'array',
        'collected_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    /**
     * Fee types
     */
    const FEE_TYPES = [
        'transaction_percentage' => 'Transaction Percentage',
        'subscription_flat' => 'Subscription (No Fee)',
        'hybrid' => 'Hybrid Model',
    ];

    /**
     * Fee statuses
     */
    const STATUSES = [
        'pending' => 'Pending',
        'collected' => 'Collected',
        'settled' => 'Settled',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ];

    /**
     * Get the payment
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the landlord
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the payout account
     */
    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(LandlordPayoutAccount::class, 'payout_account_id');
    }

    /**
     * Get fee type label
     */
    public function getFeeTypeLabelAttribute(): string
    {
        return self::FEE_TYPES[$this->fee_type] ?? $this->fee_type;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'collected', 'settled' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Scope for pending fees
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for collected fees
     */
    public function scopeCollected($query)
    {
        return $query->where('status', 'collected');
    }

    /**
     * Scope for settled fees
     */
    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Mark fee as collected
     */
    public function markCollected(): self
    {
        $this->update([
            'status' => 'collected',
            'collected_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark fee as settled
     */
    public function markSettled(): self
    {
        $this->update([
            'status' => 'settled',
            'settled_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark fee as failed
     */
    public function markFailed(?string $notes = null): self
    {
        $this->update([
            'status' => 'failed',
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Mark fee as refunded
     */
    public function markRefunded(?string $notes = null): self
    {
        $this->update([
            'status' => 'refunded',
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Get total fees collected for a period
     */
    public static function totalCollectedForPeriod($startDate, $endDate, $landlordId = null): float
    {
        $query = self::whereIn('status', ['collected', 'settled'])
            ->dateRange($startDate, $endDate);

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->sum('fee_amount');
    }

    /**
     * Get platform revenue analytics
     */
    public static function revenueAnalytics($startDate, $endDate): array
    {
        $fees = self::whereIn('status', ['collected', 'settled'])
            ->dateRange($startDate, $endDate);

        return [
            'total_gross' => $fees->sum('gross_amount'),
            'total_fees' => $fees->sum('fee_amount'),
            'total_net' => $fees->sum('net_amount'),
            'transaction_count' => $fees->count(),
            'average_fee_percentage' => $fees->avg('fee_percentage_applied'),
        ];
    }
}
