<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'subscription_id',
        'user_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'reference',
        'paystack_reference',
        'paystack_response',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paystack_response' => 'array',
        'paid_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'successful' => 'Successful',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'successful' => 'green',
            'failed' => 'red',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'paystack' => 'Paystack',
            'manual' => 'Manual Payment',
            'bank_transfer' => 'Bank Transfer',
            default => ucfirst($this->payment_method ?? 'Unknown'),
        };
    }
}
