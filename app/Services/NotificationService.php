<?php

namespace App\Services;

use App\Enums\Currency;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Services\Notification\ChannelPrioritizer;
use App\Services\Notification\ChannelSelector;
use App\Services\Notification\ChannelTransport;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Notification\NotificationRateLimiter;
use Carbon\Carbon;
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
        private readonly WhatsAppTemplateService $whatsAppTemplateService,
        private readonly PaymentLinkService $paymentLinkService,
        private readonly NotificationConfigRepositoryInterface $configRepository,
        private readonly QuietHoursService $quietHoursService,
        private readonly ChannelSelector $channelSelector,
        private readonly NotificationDispatcher $dispatcher,
        private readonly NotificationRateLimiter $rateLimiter,
        private readonly ChannelTransport $channelTransport,
        private readonly ChannelPrioritizer $channelPrioritizer
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

        if ($this->shouldDeferForQuietHours($urgency, $recipient, $landlordId)) {
            return $this->deferNotificationForQuietHours(
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

    private function resolveCurrencySymbol(array $data, int $landlordId): string
    {
        if (isset($data['currency_symbol'])) {
            return $data['currency_symbol'];
        }

        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        return ($config?->default_currency ?? Currency::default())->symbol();
    }

    private function shouldDeferForQuietHours(string $urgency, User $recipient, int $landlordId): bool
    {
        return ! $this->canBypassQuietHours($urgency) && $this->isInQuietHours($recipient, $landlordId);
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
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $paymentLink = isset($data['invoice_id'])
            ? $this->paymentLinkService->generateUrl($data['invoice_id'], 'rent_reminder')
            : route('tenant.finances.index');

        $message = sprintf(
            "Hello %s,\n\nThis is a friendly reminder that your rent of %s %s is due on %s.\n\nPay now: %s\n\nThank you.",
            $tenant->name,
            $symbol,
            number_format($data['amount'], 2),
            $data['due_date'],
            $paymentLink
        );

        $templateData = [
            'tenant_name' => $tenant->name,
            'amount' => number_format($data['amount'], 0),
            'due_date' => $data['due_date'],
        ];

        // Only include payment_link in template data if feature enabled
        // (requires Meta-approved WhatsApp template with payment_link variable)
        if (config('features.whatsapp_payment_links_enabled', false)) {
            $templateData['payment_link'] = $paymentLink;
        }

        return $this->send(
            $tenantId,
            'rent_reminder',
            'Rent Reminder - Due '.$data['due_date'],
            $message,
            array_merge($data, $templateData),
            $landlordId
        );
    }

    /**
     * Send arrears notice to tenant
     */
    public function sendArrearsNotice(int $tenantId, array $data, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $paymentLink = isset($data['invoice_id'])
            ? $this->paymentLinkService->generateUrl($data['invoice_id'], 'arrears_notice')
            : route('tenant.finances.index');

        $message = sprintf(
            "Hello %s,\n\nYou have an outstanding balance of %s %s. Please clear your arrears as soon as possible.\n\nPay now: %s\n\nThank you.",
            $tenant->name,
            $symbol,
            number_format($data['arrears_amount'], 2),
            $paymentLink
        );

        $templateData = [
            'tenant_name' => $tenant->name,
            'amount' => number_format($data['arrears_amount'], 0),
            'days_overdue' => (string) ($data['days_overdue'] ?? 0),
        ];

        // Only include payment_link in template data if feature enabled
        if (config('features.whatsapp_payment_links_enabled', false)) {
            $templateData['payment_link'] = $paymentLink;
        }

        return $this->send(
            $tenantId,
            'arrears_notice',
            'Payment Overdue - Please Clear Arrears',
            $message,
            array_merge($data, $templateData),
            $landlordId
        );
    }

    /**
     * Send invoice notification
     */
    public function sendInvoice(int $tenantId, array $invoiceData, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($invoiceData, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nYour invoice #%s for %s %s has been generated. Due date: %s.\n\nPlease login to view and pay.",
            $tenant->name,
            $invoiceData['invoice_number'],
            $symbol,
            number_format($invoiceData['total_amount'], 2),
            $invoiceData['due_date']
        );

        $templateData = [
            'tenant_name' => $tenant->name,
            'invoice_no' => $invoiceData['invoice_number'],
            'amount' => number_format($invoiceData['total_amount'], 0),
            'due_date' => $invoiceData['due_date'],
            'link' => $invoiceData['link'] ?? url('/tenant/invoices'),
        ];

        return $this->send(
            $tenantId,
            'invoice',
            'New Invoice - '.$invoiceData['invoice_number'],
            $message,
            array_merge($invoiceData, $templateData),
            $landlordId
        );
    }

    /**
     * Send payment receipt
     */
    public function sendReceipt(int $tenantId, array $receiptData, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($receiptData, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nPayment of %s %s received successfully. Receipt #%s.\n\nThank you for your payment.",
            $tenant->name,
            $symbol,
            number_format($receiptData['amount'], 2),
            $receiptData['receipt_number']
        );

        $templateData = [
            'tenant_name' => $tenant->name,
            'amount' => number_format($receiptData['amount'], 0),
            'reference' => $receiptData['receipt_number'],
            'balance' => number_format($receiptData['balance'] ?? 0, 0),
        ];

        return $this->send(
            $tenantId,
            'receipt',
            'Payment Receipt - '.$receiptData['receipt_number'],
            $message,
            array_merge($receiptData, $templateData),
            $landlordId
        );
    }

    /**
     * Send rent hike notification
     */
    public function sendRentHike(int $tenantId, array $data, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nThis is to inform you that your rent will be adjusted from %s %s to %s %s effective %s.\n\nThank you for your understanding.",
            $tenant->name,
            $symbol,
            number_format($data['old_rent'], 2),
            $symbol,
            number_format($data['new_rent'], 2),
            $data['effective_date']
        );

        return $this->send(
            $tenantId,
            'rent_hike',
            'Rent Adjustment Notice',
            $message,
            $data,
            $landlordId
        );
    }

    /**
     * Send eviction notice to tenant
     */
    public function sendEvictionNotice(int $tenantId, array $data, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nThis is a formal notice of eviction. Due to non-payment of rent, you are required to vacate the premises within the specified period.\n\nOutstanding Balance: %s %s\n\nPlease contact your landlord immediately to discuss this matter.\n\nRegards",
            $tenant->name,
            $symbol,
            number_format($data['arrears_amount'] ?? 0, 2)
        );

        return $this->send(
            $tenantId,
            'eviction_notice',
            'Eviction Notice',
            $message,
            $data,
            $landlordId
        );
    }

    /**
     * Send caretaker invitation notification to an existing user
     */
    public function sendCaretakerInvitation(int $targetUserId, array $data, int $landlordId): array
    {
        $targetUser = User::findOrFail($targetUserId);

        $message = sprintf(
            "Hello %s,\n\nYou've been invited by %s to become a caretaker for %s.\n\nPlease log in to your account to accept or decline this invitation.\n\nThis invitation expires on %s.",
            $targetUser->name,
            $data['landlord_name'],
            $data['property_name'],
            $data['expires_at'] ?? 'in 30 days'
        );

        return $this->sendInAppOnly(
            $targetUserId,
            Notification::TYPE_CARETAKER_INVITATION,
            'Caretaker Invitation from '.$data['landlord_name'],
            $message,
            $data,
            $landlordId
        );
    }

    /**
     * Send tenant invitation notification to an existing user
     */
    public function sendTenantInvitation(int $targetUserId, array $data, int $landlordId): array
    {
        $targetUser = User::findOrFail($targetUserId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nYou've been invited by %s to lease Unit %s at %s.\n\nMonthly Rent: %s %s\nDeposit: %s %s\n\nPlease log in to your account to accept or decline this invitation.\n\nThis invitation expires on %s.",
            $targetUser->name,
            $data['landlord_name'],
            $data['unit_number'],
            $data['property_name'],
            $symbol,
            number_format($data['rent_amount'] ?? 0, 2),
            $symbol,
            number_format($data['deposit_amount'] ?? 0, 2),
            $data['expires_at'] ?? 'in 30 days'
        );

        return $this->sendInAppOnly(
            $targetUserId,
            Notification::TYPE_TENANT_INVITATION,
            'Lease Invitation from '.$data['landlord_name'],
            $message,
            $data,
            $landlordId
        );
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
     * Check if recipient is currently in quiet hours.
     */
    protected function isInQuietHours(User $recipient, int $landlordId): bool
    {
        $config = $this->quietHoursService->getConfigForUser($recipient, $landlordId);

        return $this->quietHoursService->isQuietHours($config);
    }

    /**
     * Check if this urgency level can bypass quiet hours.
     * Critical and urgent notifications are never deferred.
     */
    protected function canBypassQuietHours(string $urgency): bool
    {
        return $this->quietHoursService->canBypassQuietHours($urgency);
    }

    /**
     * Get the next quiet hours end time for scheduling deferred notifications.
     */
    protected function getQuietHoursEndTime(User $recipient, int $landlordId): Carbon
    {
        $config = $this->quietHoursService->getConfigForUser($recipient, $landlordId);

        return $this->quietHoursService->getNextDeliveryTime($config);
    }

    /**
     * Create a deferred notification scheduled for after quiet hours.
     */
    protected function createDeferredNotification(
        int $landlordId,
        int $recipientId,
        string $type,
        string $channel,
        string $subject,
        string $message,
        ?array $data,
        string $urgency,
        Carbon $scheduledFor
    ): Notification {
        return Notification::create([
            'landlord_id' => $landlordId,
            'recipient_id' => $recipientId,
            'type' => $type,
            'urgency' => $urgency,
            'channel' => $channel,
            'subject' => $subject,
            'message' => $message,
            'data' => $data,
            'status' => 'pending',
            'scheduled_for' => $scheduledFor,
            'quiet_hours_suppressed' => true,
        ]);
    }

    /**
     * Defer notification until after quiet hours end.
     */
    protected function deferNotificationForQuietHours(
        int $recipientId,
        string $type,
        string $subject,
        string $message,
        ?array $data,
        int $landlordId,
        string $urgency,
        array $allowedChannels
    ): array {
        $recipient = User::findOrFail($recipientId);
        $preferences = NotificationPreference::getOrCreate($recipientId, $landlordId);
        $prioritizedChannels = $this->channelPrioritizer->prioritizeChannelsWithUrgency($preferences, $allowedChannels);
        $primaryChannel = $this->channelPrioritizer->findPrimaryChannel($prioritizedChannels, $preferences, $type);

        if (! $primaryChannel) {
            return ['error' => 'no_available_channel'];
        }

        $scheduledFor = $this->getQuietHoursEndTime($recipient, $landlordId);

        $notification = $this->createDeferredNotification(
            $landlordId,
            $recipientId,
            $type,
            $primaryChannel,
            $subject,
            $message,
            $data,
            $urgency,
            $scheduledFor
        );

        // Dispatch delayed job to send notification when quiet hours end
        SendNotificationJob::forDeferred($notification->id)
            ->delay($scheduledFor);

        Log::info('Notification deferred for quiet hours', [
            'notification_id' => $notification->id,
            'recipient_id' => $recipientId,
            'scheduled_for' => $scheduledFor->toDateTimeString(),
            'channel' => $primaryChannel,
        ]);

        return [
            $primaryChannel => 'deferred',
            'scheduled_for' => $scheduledFor->toDateTimeString(),
            'quiet_hours_suppressed' => true,
        ];
    }

    /**
     * Send a deferred notification (used by scheduler/job).
     */
    public function sendDeferredNotification(Notification $notification): bool
    {
        if (! $notification->isScheduled() && $notification->scheduled_for?->isPast()) {
            $recipient = $notification->recipient;

            if (! $recipient) {
                $notification->markAsFailed('Recipient not found');

                return false;
            }

            try {
                $sent = $this->sendViaChannel($notification, $recipient);
                if ($sent) {
                    $notification->update(['scheduled_for' => null]);
                }

                return $sent;
            } catch (\Exception $e) {
                $notification->markAsFailed($e->getMessage());
                $this->channelTransport->logChannelFailure($notification, $e);

                return false;
            }
        }

        return false;
    }
}
