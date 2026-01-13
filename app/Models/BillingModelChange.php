<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingModelChange extends Model
{
    protected $fillable = [
        'from_model',
        'to_model',
        'changed_by',
        'effective_date',
        'reason',
        'settings_snapshot',
    ];

    protected $casts = [
        'effective_date' => 'datetime',
        'settings_snapshot' => 'array',
    ];

    /**
     * Get the user who made the change
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get from model label
     */
    public function getFromModelLabelAttribute(): ?string
    {
        if (! $this->from_model) {
            return null;
        }

        return PlatformBillingSetting::BILLING_MODELS[$this->from_model] ?? $this->from_model;
    }

    /**
     * Get to model label
     */
    public function getToModelLabelAttribute(): string
    {
        return PlatformBillingSetting::BILLING_MODELS[$this->to_model] ?? $this->to_model;
    }

    /**
     * Get change description
     */
    public function getDescriptionAttribute(): string
    {
        if ($this->from_model) {
            return "Changed from {$this->from_model_label} to {$this->to_model_label}";
        }

        return "Initial setup: {$this->to_model_label}";
    }

    /**
     * Check if this was a fee percentage change (not model change)
     */
    public function isFeePercentageChange(): bool
    {
        return $this->from_model === $this->to_model;
    }

    /**
     * Scope for recent changes
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope by changed user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('changed_by', $userId);
    }
}
