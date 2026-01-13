<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use TenantScope;

    // Notification type constants
    public const TYPE_RENT_REMINDER = 'rent_reminder';

    public const TYPE_ARREARS_NOTICE = 'arrears_notice';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_RENT_HIKE = 'rent_hike';

    public const TYPE_LEASE_EXPIRY = 'lease_expiry';

    public const TYPE_LEASE_RENEWAL = 'lease_renewal';

    public const TYPE_MAINTENANCE_NOTICE = 'maintenance_notice';

    public const TYPE_GENERAL = 'general';

    public const TYPE_EVICTION_NOTICE = 'eviction_notice';

    public const TYPE_CARETAKER_INVITATION = 'caretaker_invitation';

    public const TYPE_TENANT_INVITATION = 'tenant_invitation';

    // Channel constants
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_PUSH = 'push';

    public const CHANNEL_IN_APP = 'in_app';

    // Invitation types array for easy checking
    public const INVITATION_TYPES = [
        self::TYPE_CARETAKER_INVITATION,
        self::TYPE_TENANT_INVITATION,
    ];

    protected $fillable = [
        'landlord_id',
        'recipient_id',
        'type',
        'channel',
        'subject',
        'message',
        'data',
        'status',
        'external_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Get the landlord who owns this notification
     */
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the recipient (tenant/user) of this notification
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(?string $externalId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'external_id' => $externalId,
        ]);
    }

    /**
     * Mark notification as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if notification is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if notification was sent successfully
     */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    /**
     * Check if notification failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Scope: Filter by notification type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter by channel
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: In-app notifications only
     */
    public function scopeInApp($query)
    {
        return $query->where('channel', self::CHANNEL_IN_APP);
    }

    /**
     * Scope: Invitation notifications only
     */
    public function scopeInvitations($query)
    {
        return $query->whereIn('type', self::INVITATION_TYPES);
    }

    /**
     * Scope: Unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Check if this is an invitation notification
     */
    public function isInvitation(): bool
    {
        return in_array($this->type, self::INVITATION_TYPES);
    }

    /**
     * Check if this is a caretaker invitation
     */
    public function isCaretakerInvitation(): bool
    {
        return $this->type === self::TYPE_CARETAKER_INVITATION;
    }

    /**
     * Check if this is a tenant invitation
     */
    public function isTenantInvitation(): bool
    {
        return $this->type === self::TYPE_TENANT_INVITATION;
    }

    /**
     * Get the associated invitation model from the data JSON
     *
     * @return \App\Models\Invitation|\App\Models\TenantInvitation|null
     */
    public function getInvitation()
    {
        if (! $this->isInvitation() || ! $this->data) {
            return null;
        }

        $invitationId = $this->data['invitation_id'] ?? null;
        if (! $invitationId) {
            return null;
        }

        return match ($this->type) {
            self::TYPE_CARETAKER_INVITATION => Invitation::find($invitationId),
            self::TYPE_TENANT_INVITATION => TenantInvitation::find($invitationId),
            default => null,
        };
    }

    /**
     * Get the invitation ID from the data JSON
     */
    public function getInvitationId(): ?int
    {
        if (! $this->isInvitation() || ! $this->data) {
            return null;
        }

        return $this->data['invitation_id'] ?? null;
    }

    /**
     * Get the invitation type string for frontend
     */
    public function getInvitationType(): ?string
    {
        if (! $this->isInvitation()) {
            return null;
        }

        return $this->type === self::TYPE_CARETAKER_INVITATION ? 'caretaker' : 'tenant';
    }
}
