<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInvitation extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'initiated_by',
        'unit_id',
        'email',
        'existing_user_id',
        'token',
        'rent_amount',
        'service_charge',
        'deposit_amount',
        'start_date',
        'end_date',
        'tenant_name',
        'tenant_phone',
        'tenant_id_number',
        'notification_channels',
        'email_sent_at',
        'sms_sent_at',
        'whatsapp_sent_at',
        'status',
        'accepted_at',
        'expires_at',
        'viewed_at',
    ];

    protected $casts = [
        'rent_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'notification_channels' => 'array',
        'email_sent_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    /**
     * Retrieve the model for a bound value.
     * Bypasses TenantScope for route model binding.
     * Authorization is handled in the controller methods.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::withoutGlobalScope('landlord')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    // ==================== Relationships ====================

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function existingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'existing_user_id');
    }

    // ==================== Scopes ====================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($q) {
                $q->where('status', 'pending')
                    ->where('expires_at', '<', now());
            });
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    // ==================== Helpers ====================

    /**
     * Generate a secure random token for the invitation
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Check if the invitation has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Check if the invitation has been accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if this invitation is for an existing user
     */
    public function isForExistingUser(): bool
    {
        return ! is_null($this->existing_user_id);
    }

    /**
     * Check if the invitation is still valid (pending and not expired)
     */
    public function isValid(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    /**
     * Mark the invitation as accepted
     */
    public function markAsAccepted(): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    /**
     * Mark the invitation as declined
     */
    public function markAsDeclined(): void
    {
        $this->update(['status' => 'declined']);
    }

    /**
     * Mark the invitation as viewed
     */
    public function markAsViewed(): void
    {
        if (! $this->viewed_at) {
            $this->update(['viewed_at' => now()]);
        }
    }

    /**
     * Check if the invitation has been viewed
     */
    public function isViewed(): bool
    {
        return ! is_null($this->viewed_at);
    }

    /**
     * Extend the expiration date
     */
    public function extendExpiry(int $days = 7): void
    {
        $this->update([
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->isExpired() && $this->status === 'pending') {
            return 'expired';
        }

        return $this->status;
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute(): int
    {
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Get the total move-in cost
     */
    public function getTotalMoveInCostAttribute(): float
    {
        return $this->rent_amount + $this->service_charge + $this->deposit_amount;
    }

    /**
     * Get the invitation URL
     */
    public function getAcceptUrlAttribute(): string
    {
        return route('tenant-invitations.show', $this->token);
    }

    // ==================== Channel Helpers ====================

    /**
     * Check if email channel is enabled
     */
    public function shouldSendEmail(): bool
    {
        return in_array('email', $this->notification_channels ?? ['email']);
    }

    /**
     * Check if SMS channel is enabled and phone is available
     */
    public function shouldSendSms(): bool
    {
        return in_array('sms', $this->notification_channels ?? []) && ! empty($this->tenant_phone);
    }

    /**
     * Check if WhatsApp channel is enabled and phone is available
     */
    public function shouldSendWhatsApp(): bool
    {
        return in_array('whatsapp', $this->notification_channels ?? []) && ! empty($this->tenant_phone);
    }

    /**
     * Get a list of channels that were successfully sent
     */
    public function getSentChannelsAttribute(): array
    {
        $channels = [];
        if ($this->email_sent_at) {
            $channels[] = 'email';
        }
        if ($this->sms_sent_at) {
            $channels[] = 'sms';
        }
        if ($this->whatsapp_sent_at) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }
}
