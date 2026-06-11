<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Services\Notification\ChannelPrioritizer;
use App\Services\Notification\ChannelSelector;
use App\Services\Notification\ChannelTransport;
use App\Services\Notification\DeferredNotificationHandler;
use App\Services\Notification\NotificationContentBuilder;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Notification\NotificationRateLimiter;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public const URGENCY_CHANNELS = [
        Notification::URGENCY_CRITICAL => [
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_SMS,
            Notification::CHANNEL_PUSH,
            Notification::CHANNEL_IN_APP,
        ],
        Notification::URGENCY_URGENT => [
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_PUSH,
            Notification::CHANNEL_IN_APP,
        ],
        Notification::URGENCY_IMPORTANT => [
            Notification::CHANNEL_WHATSAPP,
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_IN_APP,
        ],
        Notification::URGENCY_INFORMATIONAL => [
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_IN_APP,
        ],
    ];

    public function __construct(
        private readonly NotificationConfigRepositoryInterface $configRepository,
        private readonly ChannelSelector $channelSelector,
        private readonly NotificationDispatcher $dispatcher,
        private readonly NotificationRateLimiter $rateLimiter,
        private readonly ChannelTransport $channelTransport,
        private readonly ChannelPrioritizer $channelPrioritizer,
        private readonly DeferredNotificationHandler $deferralHandler,
        private readonly NotificationContentBuilder $contentBuilder
    ) {}

    /**
     * Send a notification to a user via urgency-based channel selection.
     * Critical notifications are sent to ALL allowed channels.
     * Other urgency levels use prioritized single channel with fallback.
     */
    public function send(
        int $recipientId,
        string $type,
        string $subject,
        string $message,
        ?array $data = null,
        ?int $landlordId = null
    ): array {
        $recipient = User::findOrFail($recipientId);
        $landlordId = $landlordId ?? $this->resolveLandlordId($recipient);

        $urgency = Notification::getUrgencyForType($type);
        $allowedChannels = $this->channelSelector->getChannelsForUrgency($urgency);

        if ($this->deferralHandler->shouldDefer($urgency, $recipient, $landlordId)) {
            return $this->deferralHandler->defer(
                $recipientId, $type, $subject, $message, $data, $landlordId, $urgency, $allowedChannels
            );
        }

        if ($urgency === Notification::URGENCY_CRITICAL) {
            return $this->sendToAllowedChannels(
                $recipientId, $type, $subject, $message, $data, $landlordId, $allowedChannels, $urgency
            );
        }

        $preferences = NotificationPreference::getOrCreate($recipientId, $landlordId);
        $primaryChannel = $this->channelSelector->selectChannel($urgency, $type, $preferences);

        if (! $primaryChannel) {
            Log::warning('No available channel for notification', compact('recipientId', 'type', 'urgency'));

            return ['error' => 'no_available_channel'];
        }

        if (! $this->rateLimiter->check($landlordId, $primaryChannel)) {
            Log::warning('Notification rate limited', compact('landlordId', 'primaryChannel'));

            return [$primaryChannel => 'rate_limited'];
        }

        $notification = $this->createNotification(
            $landlordId, $recipientId, $type, $primaryChannel, $subject, $message, $data, $urgency
        );

        $status = $this->dispatcher->dispatch(
            $notification,
            $recipient,
            fn ($n, $r) => $this->sendViaChannel($n, $r)
        );

        return [$primaryChannel => $status];
    }

    /**
     * Create an IN-APP-ONLY notification (bell + notifications page), bypassing the
     * urgency channel selector so it never also sends an email/SMS. Used when the caller
     * already sends the real document via a dedicated mailable (e.g. an owner remittance
     * advice or statement PDF) and only wants the in-app companion. Honors the recipient's
     * per-type + in_app preference.
     *
     * Pass $landlordId explicitly for non-tenant recipients (owners/caretakers): the
     * default resolveLandlordId() returns the recipient's OWN id for non-tenants.
     *
     * @return array{0: bool, 1: string} [dispatched, reason]
     */
    public function notifyInApp(
        int $recipientId,
        string $type,
        string $subject,
        string $message,
        ?array $data = null,
        ?int $landlordId = null,
    ): array {
        $recipient = User::findOrFail($recipientId);
        $landlordId = $landlordId ?? $this->resolveLandlordId($recipient);

        $preferences = NotificationPreference::getOrCreate($recipientId, $landlordId);
        // A just-created preference row holds only the keys we queried by; its per-type/
        // channel columns are DB defaults (all on) not yet hydrated in memory, so canReceive
        // would read them as null→false. Only gate on a preference the user actually has
        // (and may have customised); a brand-new one means "never opted out" → notify.
        if (! $preferences->wasRecentlyCreated && ! $preferences->canReceive($type, Notification::CHANNEL_IN_APP)) {
            return [false, 'opted_out'];
        }

        $notification = $this->createNotification(
            $landlordId, $recipientId, $type, Notification::CHANNEL_IN_APP, $subject, $message, $data
        );

        $this->channelTransport->sendInApp($notification, $recipient);

        return [true, 'sent'];
    }

    private function resolveLandlordId(User $recipient): int
    {
        return $recipient->role === 'tenant' ? $recipient->landlord_id : $recipient->id;
    }

    /**
     * Send a notification to ALL enabled channels (for critical notifications).
     */
    public function sendToAllChannels(
        int $recipientId,
        string $type,
        string $subject,
        string $message,
        ?array $data = null,
        ?int $landlordId = null
    ): array {
        $recipient = User::findOrFail($recipientId);

        if (! $landlordId) {
            $landlordId = $recipient->role === 'tenant'
                ? $recipient->landlord_id
                : $recipient->id;
        }

        $preferences = NotificationPreference::getOrCreate($recipientId, $landlordId);
        $channels = $this->channelPrioritizer->prioritizeChannels($preferences);
        $urgency = Notification::getUrgencyForType($type);
        $results = [];

        foreach ($channels as $channel) {
            if ($preferences->canReceive($type, $channel)) {
                if (! $this->rateLimiter->check($landlordId, $channel)) {
                    $results[$channel] = 'rate_limited';

                    continue;
                }

                $notification = $this->createNotification(
                    $landlordId,
                    $recipientId,
                    $type,
                    $channel,
                    $subject,
                    $message,
                    $data,
                    $urgency
                );

                try {
                    $sent = $this->sendViaChannel($notification, $recipient);
                    $results[$channel] = $sent ? 'sent' : 'failed';
                } catch (\Exception $e) {
                    $notification->markAsFailed($e->getMessage());
                    $this->channelTransport->logChannelFailure($notification, $e);
                    $results[$channel] = 'failed';
                }
            }
        }

        return $results;
    }

    /**
     * Send a notification to specific allowed channels based on urgency.
     * Used for critical notifications that require multiple channels.
     */
    private function sendToAllowedChannels(
        int $recipientId,
        string $type,
        string $subject,
        string $message,
        ?array $data,
        int $landlordId,
        array $allowedChannels,
        string $urgency
    ): array {
        $recipient = User::findOrFail($recipientId);
        $preferences = NotificationPreference::getOrCreate($recipientId, $landlordId);
        $results = [];

        foreach ($allowedChannels as $channel) {
            if ($preferences->canReceive($type, $channel)) {
                if (! $this->rateLimiter->check($landlordId, $channel)) {
                    $results[$channel] = 'rate_limited';

                    continue;
                }

                $notification = $this->createNotification(
                    $landlordId,
                    $recipientId,
                    $type,
                    $channel,
                    $subject,
                    $message,
                    $data,
                    $urgency
                );

                try {
                    $sent = $this->sendViaChannel($notification, $recipient);
                    $results[$channel] = $sent ? 'sent' : 'failed';
                } catch (\Exception $e) {
                    $notification->markAsFailed($e->getMessage());
                    $this->channelTransport->logChannelFailure($notification, $e);
                    $results[$channel] = 'failed';
                }
            }
        }

        return $results;
    }

    /**
     * Find the first channel that user can receive notifications on.
     */
    /**
     * Get allowed channels for a given urgency level.
     */
    public function getChannelsForUrgency(string $urgency): array
    {
        return $this->channelSelector->getChannelsForUrgency($urgency);
    }

    /**
     * Send bulk notifications to multiple recipients
     */
    public function sendBulk(
        array $recipientIds,
        string $type,
        string $subject,
        string $message,
        ?array $data,
        int $landlordId,
        array $channels = ['email', 'sms', 'whatsapp']
    ): array {
        $results = [
            'total' => count($recipientIds),
            'sent' => 0,
            'failed' => 0,
            'channels' => [],
        ];

        foreach ($recipientIds as $recipientId) {
            try {
                $channelResults = $this->send(
                    $recipientId,
                    $type,
                    $subject,
                    $message,
                    $data,
                    $landlordId
                );

                foreach ($channelResults as $channel => $status) {
                    if (! isset($results['channels'][$channel])) {
                        $results['channels'][$channel] = ['sent' => 0, 'failed' => 0];
                    }

                    if ($status === 'sent') {
                        $results['channels'][$channel]['sent']++;
                    } else {
                        $results['channels'][$channel]['failed']++;
                    }
                }

                $results['sent']++;
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Bulk notification failed', [
                    'recipient_id' => $recipientId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Create a notification record with timeout tracking for fallback
     */
    private function createNotification(
        int $landlordId,
        int $recipientId,
        string $type,
        string $channel,
        string $subject,
        string $message,
        ?array $data,
        ?string $urgency = null
    ): Notification {
        return Notification::create([
            'landlord_id' => $landlordId,
            'recipient_id' => $recipientId,
            'type' => $type,
            'urgency' => $urgency ?? Notification::getUrgencyForType($type),
            'channel' => $channel,
            'subject' => $subject,
            'message' => $message,
            'data' => $data,
            'status' => 'pending',
            'timeout_at' => Notification::calculateTimeoutAt($channel),
            'primary_attempt_at' => now(),
        ]);
    }

    /**
     * Send notification via specific channel.
     * Can override channel for fallback scenarios.
     */
    public function sendViaChannel(Notification $notification, User $recipient, ?string $overrideChannel = null): bool
    {
        return $this->channelTransport->sendViaChannel($notification, $recipient, $overrideChannel);
    }

    /**
     * Send rent reminder to tenant
     */
    public function sendRentReminder(int $tenantId, array $data, int $landlordId): array
    {
        [$subject, $message, $payload] = $this->contentBuilder->rentReminder($tenantId, $data, $landlordId);

        return $this->send($tenantId, 'rent_reminder', $subject, $message, $payload, $landlordId);
    }

    /**
     * Send arrears notice to tenant
     */
    public function sendArrearsNotice(int $tenantId, array $data, int $landlordId): array
    {
        [$subject, $message, $payload] = $this->contentBuilder->arrearsNotice($tenantId, $data, $landlordId);

        return $this->send($tenantId, 'arrears_notice', $subject, $message, $payload, $landlordId);
    }

    /**
     * Send invoice notification
     */
    public function sendInvoice(int $tenantId, array $invoiceData, int $landlordId): array
    {
        [$subject, $message, $payload] = $this->contentBuilder->invoice($tenantId, $invoiceData, $landlordId);

        return $this->send($tenantId, 'invoice', $subject, $message, $payload, $landlordId);
    }

    /**
     * Send payment receipt
     */
    public function sendReceipt(int $tenantId, array $receiptData, int $landlordId): array
    {
        [$subject, $message, $payload] = $this->contentBuilder->receipt($tenantId, $receiptData, $landlordId);

        return $this->send($tenantId, 'receipt', $subject, $message, $payload, $landlordId);
    }

    /**
     * Send rent hike notification
     */
    public function sendRentHike(int $tenantId, array $data, int $landlordId): array
    {
        [$subject, $message, $payload] = $this->contentBuilder->rentHike($tenantId, $data, $landlordId);

        return $this->send($tenantId, 'rent_hike', $subject, $message, $payload, $landlordId);
    }

    /**
     * Send eviction notice to tenant
     */
    public function sendEvictionNotice(int $tenantId, array $data, int $landlordId): array
    {
        [$subject, $message, $payload] = $this->contentBuilder->evictionNotice($tenantId, $data, $landlordId);

        return $this->send($tenantId, 'eviction_notice', $subject, $message, $payload, $landlordId);
    }

    /**
     * Send caretaker invitation notification to an existing user
     */
    public function sendCaretakerInvitation(int $targetUserId, array $data, int $landlordId): array
    {
        [$subject, $message, $payload] = $this->contentBuilder->caretakerInvitation($targetUserId, $data, $landlordId);

        return $this->sendInAppOnly($targetUserId, Notification::TYPE_CARETAKER_INVITATION, $subject, $message, $payload, $landlordId);
    }

    /**
     * Send tenant invitation notification to an existing user
     */
    public function sendTenantInvitation(int $targetUserId, array $data, int $landlordId): array
    {
        [$subject, $message, $payload] = $this->contentBuilder->tenantInvitation($targetUserId, $data, $landlordId);

        return $this->sendInAppOnly($targetUserId, Notification::TYPE_TENANT_INVITATION, $subject, $message, $payload, $landlordId);
    }

    /**
     * Send notification via in-app channel only (bypasses preferences for in_app)
     * Used for invitations that should always appear in the app
     */
    public function sendInAppOnly(
        int $recipientId,
        string $type,
        string $subject,
        string $message,
        ?array $data = null,
        ?int $landlordId = null
    ): array {
        $recipient = User::findOrFail($recipientId);

        if (! $landlordId) {
            $landlordId = $recipient->role === 'tenant'
                ? $recipient->landlord_id
                : $recipient->id;
        }

        $results = [];

        // Always create in-app notification for invitations
        $notification = $this->createNotification(
            $landlordId,
            $recipientId,
            $type,
            'in_app',
            $subject,
            $message,
            $data
        );

        try {
            $sent = $this->channelTransport->sendInApp($notification, $recipient);
            $results['in_app'] = $sent ? 'sent' : 'failed';
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            $results['in_app'] = 'failed';
            Log::error('In-app notification failed', [
                'error' => $e->getMessage(),
                'notification_id' => $notification->id,
            ]);
        }

        return $results;
    }

    /**
     * Get remaining rate limit for a channel.
     *
     * @return array{hourly: array{remaining: int, limit: int, resets_at: int}, daily: array{remaining: int, limit: int, resets_at: int}}
     */
    public function getRateLimitRemaining(int $landlordId, string $channel): array
    {
        return $this->rateLimiter->remaining($landlordId, $channel);
    }

    /**
     * Reset rate limits for a landlord.
     */
    public function resetRateLimits(int $landlordId, ?string $channel = null): void
    {
        $this->rateLimiter->reset($landlordId, $channel);
    }

    /**
     * Notify landlord when a tenant is unreachable on all channels.
     */
    public function notifyLandlordUnreachable(Notification $failedNotification): void
    {
        $tenant = $failedNotification->recipient;
        $landlord = $failedNotification->landlord;

        if (! $tenant || ! $landlord) {
            Log::warning('notifyLandlordUnreachable: Missing tenant or landlord', [
                'notification_id' => $failedNotification->id,
            ]);

            return;
        }

        $attemptedChannels = $failedNotification->fallback_channel
            ? array_slice(
                Notification::FALLBACK_CHAIN,
                0,
                array_search($failedNotification->fallback_channel, Notification::FALLBACK_CHAIN) + 1
            )
            : [$failedNotification->channel];

        $message = sprintf(
            "Unable to reach tenant %s.\n\nOriginal notification: %s\nType: %s\nAttempted channels: %s\n\nThe tenant may have invalid contact details. Please verify their phone number, WhatsApp, and email address.",
            $tenant->name,
            $failedNotification->subject,
            $failedNotification->type,
            implode(', ', $attemptedChannels)
        );

        $notification = Notification::create([
            'landlord_id' => $landlord->id,
            'recipient_id' => $landlord->id,
            'type' => Notification::TYPE_GENERAL,
            'channel' => Notification::CHANNEL_IN_APP,
            'subject' => 'Tenant Unreachable: '.$tenant->name,
            'message' => $message,
            'data' => [
                'failed_notification_id' => $failedNotification->id,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'original_type' => $failedNotification->type,
                'attempted_channels' => $attemptedChannels,
            ],
            'status' => 'pending',
        ]);

        $notification->markAsSent();

        Log::info('Landlord notified about unreachable tenant', [
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'failed_notification_id' => $failedNotification->id,
        ]);
    }

    /**
     * Send a deferred notification (used by scheduler/job).
     */
    public function sendDeferredNotification(Notification $notification): bool
    {
        return $this->deferralHandler->sendDeferred($notification);
    }
}
