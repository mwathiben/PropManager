<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    // OBS-7: capture every plan/status/billing change so a billing
    // dispute can be reconstructed from audit_logs without trusting
    // the (mutable) row. Pre-fix tier transitions were silent.
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'cancel_reason',
        'cancel_feedback',
        'ends_at',
        'paystack_subscription_code',
        'paystack_customer_code',
        'stripe_subscription_code',
        'stripe_customer_code',
    ];

    public const CANCEL_REASONS = [
        'too_expensive',
        'missing_features',
        'switching_competitor',
        'business_closing',
        'technical_issues',
        'other',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
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
        return $this->status === SubscriptionStatus::Active || $this->onTrial();
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing && $this->trial_ends_at?->isFuture();
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
        return $this->status === SubscriptionStatus::PastDue;
    }

    public function needsPayment(): bool
    {
        return $this->isPastDue() || ($this->current_period_end?->isPast() && ! $this->cancelled());
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }
}
