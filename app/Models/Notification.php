<?php

namespace App\Models;

use App\Enums\NotificationStatus;
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

    // Phase-82 DOC-REMINDERS-1: document expiry reminder.
    public const TYPE_DOCUMENT_EXPIRY = 'document_expiry';

    public const TYPE_MAINTENANCE_NOTICE = 'maintenance_notice';

    public const TYPE_GENERAL = 'general';

    public const TYPE_EVICTION_NOTICE = 'eviction_notice';

    public const TYPE_CARETAKER_INVITATION = 'caretaker_invitation';

    public const TYPE_TENANT_INVITATION = 'tenant_invitation';

    // Phase-63 INBOX-NOTIFY-2: fallback channel for unread inbox messages.
    public const TYPE_NEW_MESSAGE = 'new_message';

    // Phase-85 DISPUTE-2: chargeback/dispute raised against a payment.
    public const TYPE_PAYMENT_DISPUTE = 'payment_dispute';

    // Phase-88 WATER-READING-CYCLE: caretaker reminded to take readings.
    public const TYPE_WATER_READING_DUE = 'water_reading_due';

    // Phase-88: landlord reminded to review water readings / auto-approval notice.
    public const TYPE_WATER_REVIEW_DUE = 'water_review_due';

    // Phase-90: tenant warned of water arrears / pending disconnection.
    public const TYPE_WATER_ARREARS = 'water_arrears';

    // Phase-97: water client billed for a period (a new water-client charge).
    public const TYPE_WATER_BILL_DUE = 'water_bill_due';

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

    // Fallback chain order (WhatsApp → SMS → Email → In-app)
    public const FALLBACK_CHAIN = [
        self::CHANNEL_WHATSAPP,
        self::CHANNEL_SMS,
        self::CHANNEL_EMAIL,
        self::CHANNEL_IN_APP,
    ];

    // Channel timeout configuration (in minutes)
    public const CHANNEL_TIMEOUTS = [
        self::CHANNEL_WHATSAPP => 60,    // 1 hour
        self::CHANNEL_SMS => 30,         // 30 minutes
        self::CHANNEL_EMAIL => null,     // No timeout
        self::CHANNEL_PUSH => null,      // No timeout
        self::CHANNEL_IN_APP => null,    // No timeout
    ];

    // Maximum retries per channel
    public const CHANNEL_MAX_RETRIES = [
        self::CHANNEL_WHATSAPP => 2,
        self::CHANNEL_SMS => 1,
        self::CHANNEL_EMAIL => 3,
        self::CHANNEL_PUSH => 1,
        self::CHANNEL_IN_APP => 0,
    ];

    // Urgency level constants
    public const URGENCY_CRITICAL = 'critical';

    public const URGENCY_URGENT = 'urgent';

    public const URGENCY_IMPORTANT = 'important';

    public const URGENCY_INFORMATIONAL = 'informational';

    // Map notification types to their urgency level
    public const TYPE_URGENCY_MAP = [
        self::TYPE_EVICTION_NOTICE => self::URGENCY_CRITICAL,
        self::TYPE_ARREARS_NOTICE => self::URGENCY_URGENT,
        self::TYPE_LEASE_EXPIRY => self::URGENCY_URGENT,
        // IMPORTANT (not URGENT) so it reaches landlords via email + in-app by
        // default — the URGENT channel set (whatsapp/push/in_app) is off by default.
        self::TYPE_PAYMENT_DISPUTE => self::URGENCY_IMPORTANT,
        self::TYPE_INVOICE => self::URGENCY_IMPORTANT,
        self::TYPE_RENT_REMINDER => self::URGENCY_IMPORTANT,
        self::TYPE_RENT_HIKE => self::URGENCY_IMPORTANT,
        self::TYPE_LEASE_RENEWAL => self::URGENCY_IMPORTANT,
        self::TYPE_DOCUMENT_EXPIRY => self::URGENCY_IMPORTANT,
        // IMPORTANT so caretaker/landlord get them via email + in-app by default.
        self::TYPE_WATER_READING_DUE => self::URGENCY_IMPORTANT,
        self::TYPE_WATER_REVIEW_DUE => self::URGENCY_IMPORTANT,
        self::TYPE_WATER_ARREARS => self::URGENCY_IMPORTANT,
        self::TYPE_WATER_BILL_DUE => self::URGENCY_IMPORTANT,
        self::TYPE_CARETAKER_INVITATION => self::URGENCY_IMPORTANT,
        self::TYPE_TENANT_INVITATION => self::URGENCY_IMPORTANT,
        self::TYPE_RECEIPT => self::URGENCY_INFORMATIONAL,
        self::TYPE_MAINTENANCE_NOTICE => self::URGENCY_INFORMATIONAL,
        self::TYPE_GENERAL => self::URGENCY_INFORMATIONAL,
        self::TYPE_NEW_MESSAGE => self::URGENCY_IMPORTANT,
    ];

    protected $fillable = [
        'landlord_id',
        'recipient_id',
        'type',
        'urgency',
        'channel',
        'fallback_channel',
        'subject',
        'message',
        'data',
        'status',
        'external_id',
        'error_message',
        'delivery_reason_code',
        'retry_count',
        'sent_at',
        'delivered_at',
        'read_at',
        'fallback_sent_at',
        'timeout_at',
        'primary_attempt_at',
        'scheduled_for',
        'quiet_hours_suppressed',
    ];

    protected $casts = [
        'status' => NotificationStatus::class,
        'data' => 'array',
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'fallback_sent_at' => 'datetime',
        'timeout_at' => 'datetime',
        'primary_attempt_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'quiet_hours_suppressed' => 'boolean',
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
            'status' => NotificationStatus::Sent,
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
            'status' => NotificationStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => NotificationStatus::Read,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => NotificationStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Update notification status from webhook callback
     */
    public function updateFromWebhook(string $status, ?string $errorCode = null, ?string $errorMessage = null): void
    {
        $enumStatus = NotificationStatus::from($status);
        $data = ['status' => $enumStatus];

        if ($enumStatus === NotificationStatus::Delivered) {
            $data['delivered_at'] = now();
        } elseif ($enumStatus === NotificationStatus::Read) {
            $data['read_at'] = now();
        } elseif ($enumStatus === NotificationStatus::Failed) {
            if ($errorCode) {
                $data['delivery_reason_code'] = $errorCode;
            }
            if ($errorMessage) {
                $data['error_message'] = $errorMessage;
            }
        }

        $this->update($data);
    }

    /**
     * Check if notification is pending
     */
    public function isPending(): bool
    {
        return $this->status === NotificationStatus::Pending;
    }

    /**
     * Check if notification was sent successfully
     */
    public function isSent(): bool
    {
        return in_array($this->status, [NotificationStatus::Sent, NotificationStatus::Delivered, NotificationStatus::Read]);
    }

    /**
     * Check if notification failed
     */
    public function isFailed(): bool
    {
        return $this->status === NotificationStatus::Failed;
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
    public function scopeByStatus($query, NotificationStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', NotificationStatus::Pending);
    }

    /**
     * Scope: Failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', NotificationStatus::Failed);
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
     * Get urgency level for a notification type
     */
    public static function getUrgencyForType(string $type): string
    {
        return self::TYPE_URGENCY_MAP[$type] ?? self::URGENCY_INFORMATIONAL;
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

    /**
     * Check if this notification is stuck (timed out and needs fallback)
     */
    public function isStuck(): bool
    {
        if ($this->status !== NotificationStatus::Pending && $this->status !== NotificationStatus::Sent) {
            return false;
        }

        if (! $this->timeout_at) {
            return false;
        }

        return $this->timeout_at->isPast();
    }

    /**
     * Check if this notification should fallback to another channel
     */
    public function shouldFallback(): bool
    {
        if (! $this->isStuck() && $this->status !== NotificationStatus::Failed) {
            return false;
        }

        $currentChannel = $this->fallback_channel ?? $this->channel;
        $maxRetries = self::CHANNEL_MAX_RETRIES[$currentChannel] ?? 0;

        if ($this->retry_count < $maxRetries) {
            return false;
        }

        return $this->getNextFallbackChannel() !== null;
    }

    /**
     * Get the next channel in the fallback chain
     */
    public function getNextFallbackChannel(): ?string
    {
        $currentChannel = $this->fallback_channel ?? $this->channel;
        $currentIndex = array_search($currentChannel, self::FALLBACK_CHAIN);

        if ($currentIndex === false) {
            return null;
        }

        $nextIndex = $currentIndex + 1;

        if ($nextIndex >= count(self::FALLBACK_CHAIN)) {
            return null;
        }

        return self::FALLBACK_CHAIN[$nextIndex];
    }

    /**
     * Check if all channels have been exhausted
     */
    public function hasExhaustedAllChannels(): bool
    {
        $currentChannel = $this->fallback_channel ?? $this->channel;
        $lastChannel = end(self::FALLBACK_CHAIN);

        return $currentChannel === $lastChannel && $this->status === NotificationStatus::Failed;
    }

    /**
     * Get timeout duration for a channel in minutes
     */
    public static function getChannelTimeout(string $channel): ?int
    {
        return self::CHANNEL_TIMEOUTS[$channel] ?? null;
    }

    /**
     * Calculate timeout timestamp for a channel
     */
    public static function calculateTimeoutAt(string $channel): ?\Carbon\Carbon
    {
        $minutes = self::getChannelTimeout($channel);

        if ($minutes === null) {
            return null;
        }

        return now()->addMinutes($minutes);
    }

    /**
     * Scope: Stuck notifications that need fallback processing
     */
    public function scopeStuck($query)
    {
        return $query
            ->whereIn('status', [NotificationStatus::Pending, NotificationStatus::Sent])
            ->whereNotNull('timeout_at')
            ->where('timeout_at', '<=', now());
    }

    /**
     * Scope: Failed notifications that need fallback
     */
    public function scopeNeedsFallback($query)
    {
        return $query
            ->where(function ($q) {
                $q->where('status', NotificationStatus::Failed)
                    ->orWhere(function ($q2) {
                        $q2->whereIn('status', [NotificationStatus::Pending, NotificationStatus::Sent])
                            ->whereNotNull('timeout_at')
                            ->where('timeout_at', '<=', now());
                    });
            })
            ->whereNull('fallback_channel')
            ->orWhere(function ($q) {
                $q->whereNotNull('fallback_channel')
                    ->where('fallback_channel', '!=', self::CHANNEL_IN_APP);
            });
    }

    /**
     * Increment retry count
     */
    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }

    /**
     * Mark as sent via fallback channel
     */
    public function markAsSentViaFallback(string $channel, ?string $externalId = null): void
    {
        $this->update([
            'fallback_channel' => $channel,
            'fallback_sent_at' => now(),
            'status' => NotificationStatus::Sent,
            'external_id' => $externalId,
            'timeout_at' => self::calculateTimeoutAt($channel),
            'retry_count' => 0,
        ]);
    }

    /**
     * Scope: Scheduled notifications ready to send
     */
    public function scopeReadyToSend($query)
    {
        return $query
            ->where('status', NotificationStatus::Pending)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now());
    }

    /**
     * Check if notification was suppressed due to quiet hours
     */
    public function wasQuietHoursSuppressed(): bool
    {
        return $this->quiet_hours_suppressed === true;
    }

    /**
     * Check if notification is scheduled for later
     */
    public function isScheduled(): bool
    {
        return $this->scheduled_for !== null && $this->scheduled_for->isFuture();
    }
}
