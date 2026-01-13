<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'ends_at',
        'paystack_subscription_code',
        'paystack_customer_code',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->onTrial();
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function cancelled(): bool
    {
        return ! is_null($this->cancelled_at);
    }

    public function onGracePeriod(): bool
    {
        return $this->cancelled() && $this->ends_at?->isFuture();
    }

    public function ended(): bool
    {
        return $this->cancelled() && $this->ends_at?->isPast();
    }

    public function daysUntilEnd(): int
    {
        $endDate = $this->ends_at ?? $this->current_period_end;

        return now()->diffInDays($endDate, false);
    }

    public function daysUntilTrialEnd(): int
    {
        if (! $this->trial_ends_at) {
            return 0;
        }

        return now()->diffInDays($this->trial_ends_at, false);
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function needsPayment(): bool
    {
        return $this->isPastDue() || ($this->current_period_end?->isPast() && ! $this->cancelled());
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'cancelled' => 'Cancelled',
            'past_due' => 'Past Due',
            'trialing' => 'Trial',
            'paused' => 'Paused',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'cancelled' => 'gray',
            'past_due' => 'red',
            'trialing' => 'blue',
            'paused' => 'yellow',
            default => 'gray',
        };
    }
}
