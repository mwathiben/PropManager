<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Services\Notification\ChannelSelector;
use App\Services\Notification\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class NotificationService
{
    private const RATE_LIMIT_PER_HOUR = 100;

    private const RATE_LIMIT_PER_DAY = 1000;

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
        private readonly NotificationDispatcher $dispatcher
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

        if (! $this->checkRateLimits($landlordId, $primaryChannel)) {
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

    private function resolveLandlordId(User $recipient): int
    {
        return $recipient->role === 'tenant' ? $recipient->landlord_id : $recipient->id;
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
        $channels = $this->prioritizeChannels($preferences);
        $urgency = Notification::getUrgencyForType($type);
        $results = [];

        foreach ($channels as $channel) {
            if ($preferences->canReceive($type, $channel)) {
                if (! $this->checkRateLimits($landlordId, $channel)) {
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
                if (! $this->checkRateLimits($landlordId, $channel)) {
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
                    $results[$channel] = 'failed';
                }
            }
        }

        return $results;
    }

    /**
     * Find the first channel that user can receive notifications on.
     */
    private function findPrimaryChannel(array $channels, NotificationPreference $preferences, string $type): ?string
    {
        foreach ($channels as $channel) {
            if ($preferences->canReceive($type, $channel)) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Get prioritized channel order based on user's WhatsApp availability.
     * WhatsApp is promoted to first position when user has valid whatsapp_number and whatsapp_enabled.
     */
    private function prioritizeChannels(NotificationPreference $preferences): array
    {
        $defaultOrder = ['email', 'sms', 'whatsapp', 'push', 'in_app'];

        $hasValidWhatsApp = $preferences->whatsapp_enabled
            && ! empty($preferences->whatsapp_number)
            && $preferences->isValidE164WhatsAppNumber();

        if ($hasValidWhatsApp) {
            return ['whatsapp', 'sms', 'email', 'push', 'in_app'];
        }

        return $defaultOrder;
    }

    /**
     * Get allowed channels for a given urgency level.
     */
    public function getChannelsForUrgency(string $urgency): array
    {
        return $this->channelSelector->getChannelsForUrgency($urgency);
    }

    /**
     * Filter and prioritize channels based on urgency and user preferences.
     */
    private function prioritizeChannelsWithUrgency(NotificationPreference $preferences, array $allowedChannels): array
    {
        $prioritized = $this->prioritizeChannels($preferences);

        return array_values(array_intersect($prioritized, $allowedChannels));
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
        $channel = $overrideChannel ?? $notification->channel;

        return match ($channel) {
            'email' => $this->sendEmail($notification, $recipient),
            'sms' => $this->sendSms($notification, $recipient),
            'whatsapp' => $this->sendWhatsApp($notification, $recipient),
            'push' => $this->sendPush($notification, $recipient),
            'in_app' => $this->sendInApp($notification, $recipient),
            default => false,
        };
    }

    /**
     * Send in-app notification (just marks as sent - stored in DB for display)
     */
    private function sendInApp(Notification $notification, User $recipient): bool
    {
        // In-app notifications are immediately available once created in the database
        // They're visible in the notification bell and notifications page
        $notification->markAsSent();

        // Broadcast real-time update to recipient's notification bell
        event(new \App\Events\NewNotification($notification));

        return true;
    }

    /**
     * Send email notification
     */
    private function sendEmail(Notification $notification, User $recipient): bool
    {
        try {
            Mail::send('emails.notification', [
                'subject' => $notification->subject,
                'message' => $notification->message,
                'data' => $notification->data,
                'recipient' => $recipient,
            ], function ($mail) use ($recipient, $notification) {
                $mail->to($recipient->email)
                    ->subject($notification->subject);
            });

            $notification->markAsSent();

            return true;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSms(Notification $notification, User $recipient): bool
    {
        $provider = $this->configRepository->getSmsProvider($notification->landlord_id);

        if ($provider === 'none') {
            $notification->markAsFailed('SMS provider not configured');

            return false;
        }

        return match ($provider) {
            'twilio' => $this->sendViaTwilio($notification, $recipient),
            'africas_talking' => $this->sendViaAfricasTalking($notification, $recipient),
            default => false,
        };
    }

    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio(Notification $notification, User $recipient): bool
    {
        $credentials = $this->configRepository->getTwilioCredentials($notification->landlord_id);
        $accountSid = $credentials['account_sid'];
        $authToken = $credentials['auth_token'];
        $fromNumber = $credentials['phone_number'];

        if (! $accountSid || ! $authToken || ! $fromNumber) {
            $notification->markAsFailed('Twilio credentials not configured');

            return false;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $recipient->mobile_number,
                    'Body' => $notification->message,
                ]);

            if ($response->successful()) {
                $notification->markAsSent($response->json('sid'));

                return true;
            }

            $notification->markAsFailed($response->json('message', 'Unknown error'));

            return false;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Send SMS via Africa's Talking
     */
    private function sendViaAfricasTalking(Notification $notification, User $recipient): bool
    {
        $credentials = $this->configRepository->getAfricasTalkingCredentials($notification->landlord_id);
        $apiKey = $credentials['api_key'];
        $username = $credentials['username'];
        $from = $credentials['from'];

        if (! $apiKey || ! $username) {
            $notification->markAsFailed("Africa's Talking credentials not configured");

            return false;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'apiKey' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.africastalking.com/version1/messaging', [
                'username' => $username,
                'to' => $recipient->mobile_number,
                'message' => $notification->message,
                'from' => $from,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['SMSMessageData']['Recipients'][0]['status'])
                    && $data['SMSMessageData']['Recipients'][0]['status'] === 'Success') {
                    $notification->markAsSent($data['SMSMessageData']['Recipients'][0]['messageId'] ?? null);

                    return true;
                }
            }

            $notification->markAsFailed($response->body());

            return false;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Send WhatsApp notification via Twilio
     *
     * Uses Meta-approved templates when available (ContentSid + ContentVariables).
     * Falls back to plain text (Body) when template is not approved.
     */
    private function sendWhatsApp(Notification $notification, User $recipient): bool
    {
        $twilioCredentials = $this->configRepository->getTwilioCredentials($notification->landlord_id);
        $accountSid = $twilioCredentials['account_sid'];
        $authToken = $twilioCredentials['auth_token'];
        $fromNumber = $this->configRepository->getWhatsAppNumber($notification->landlord_id);

        if (! $accountSid || ! $authToken || ! $fromNumber) {
            $notification->markAsFailed('WhatsApp credentials not configured');

            return false;
        }

        $preferences = NotificationPreference::where('user_id', $recipient->id)
            ->where('landlord_id', $notification->landlord_id)
            ->first();

        $toNumber = $preferences?->whatsapp_number ?? $recipient->mobile_number;

        try {
            $payload = [
                'From' => 'whatsapp:'.$fromNumber,
                'To' => 'whatsapp:'.$toNumber,
            ];

            $templateType = $this->mapNotificationTypeToTemplate($notification->type);

            if ($templateType && $this->whatsAppTemplateService->isApproved($templateType, $notification->landlord_id)) {
                $templateData = $notification->data ?? [];
                $payload['ContentSid'] = $this->whatsAppTemplateService->getContentSid($templateType, $notification->landlord_id);
                $payload['ContentVariables'] = json_encode(
                    $this->whatsAppTemplateService->renderVariables($templateType, $templateData)
                );
            } else {
                $payload['Body'] = $notification->message;
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

            if ($response->successful()) {
                $notification->markAsSent($response->json('sid'));

                return true;
            }

            $notification->markAsFailed($response->json('message', 'Unknown error'));

            return false;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Map notification type to WhatsApp template type.
     */
    private function mapNotificationTypeToTemplate(string $notificationType): ?string
    {
        return match ($notificationType) {
            'rent_reminder' => 'rent_reminder',
            'arrears_notice' => 'arrears_notice',
            'invoice' => 'invoice_ready',
            'receipt' => 'payment_received',
            'maintenance_update' => 'maintenance_update',
            'lease_renewal' => 'lease_renewal',
            default => null,
        };
    }

    /**
     * Send push notification
     */
    private function sendPush(Notification $notification, User $recipient): bool
    {
        try {
            $pushService = app(PushNotificationService::class);

            // Check if push is configured
            if (! $pushService->isConfigured($notification->landlord_id)) {
                $notification->markAsFailed('Push notifications not configured');

                return false;
            }

            // Check if user has push subscriptions
            $subscriptions = $pushService->getUserSubscriptions($recipient->id);
            if ($subscriptions->isEmpty()) {
                $notification->markAsFailed('No push subscriptions for user');

                return false;
            }

            $success = $pushService->send(
                $recipient->id,
                $notification->subject ?? 'New Notification',
                $notification->message,
                $notification->data,
                $notification->landlord_id
            );

            if ($success) {
                $notification->markAsSent();

                return true;
            }

            $notification->markAsFailed('Push notification delivery failed');

            return false;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Send rent reminder to tenant
     */
    public function sendRentReminder(int $tenantId, array $data, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);

        $paymentLink = isset($data['invoice_id'])
            ? $this->paymentLinkService->generateUrl($data['invoice_id'], 'rent_reminder')
            : route('tenant.finances.index');

        $message = sprintf(
            "Hello %s,\n\nThis is a friendly reminder that your rent of KES %s is due on %s.\n\nPay now: %s\n\nThank you.",
            $tenant->name,
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

        $paymentLink = isset($data['invoice_id'])
            ? $this->paymentLinkService->generateUrl($data['invoice_id'], 'arrears_notice')
            : route('tenant.finances.index');

        $message = sprintf(
            "Hello %s,\n\nYou have an outstanding balance of KES %s. Please clear your arrears as soon as possible.\n\nPay now: %s\n\nThank you.",
            $tenant->name,
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

        $message = sprintf(
            "Hello %s,\n\nYour invoice #%s for KES %s has been generated. Due date: %s.\n\nPlease login to view and pay.",
            $tenant->name,
            $invoiceData['invoice_number'],
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

        $message = sprintf(
            "Hello %s,\n\nPayment of KES %s received successfully. Receipt #%s.\n\nThank you for your payment.",
            $tenant->name,
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

        $message = sprintf(
            "Hello %s,\n\nThis is to inform you that your rent will be adjusted from KES %s to KES %s effective %s.\n\nThank you for your understanding.",
            $tenant->name,
            number_format($data['old_rent'], 2),
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

        $message = sprintf(
            "Hello %s,\n\nThis is a formal notice of eviction. Due to non-payment of rent, you are required to vacate the premises within the specified period.\n\nOutstanding Balance: KES %s\n\nPlease contact your landlord immediately to discuss this matter.\n\nRegards",
            $tenant->name,
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

        $message = sprintf(
            "Hello %s,\n\nYou've been invited by %s to lease Unit %s at %s.\n\nMonthly Rent: KES %s\nDeposit: KES %s\n\nPlease log in to your account to accept or decline this invitation.\n\nThis invitation expires on %s.",
            $targetUser->name,
            $data['landlord_name'],
            $data['unit_number'],
            $data['property_name'],
            number_format($data['rent_amount'] ?? 0, 2),
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
            $sent = $this->sendInApp($notification, $recipient);
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
     * Check rate limits for notification channel
     */
    private function checkRateLimits(int $landlordId, string $channel): bool
    {
        if ($channel === 'in_app') {
            return true;
        }

        $hourlyKey = "notifications:{$landlordId}:{$channel}:hourly";
        $dailyKey = "notifications:{$landlordId}:{$channel}:daily";

        $rateLimits = $this->configRepository->getRateLimits($landlordId);
        $hourlyLimit = $rateLimits['hourly'];
        $dailyLimit = $rateLimits['daily'];

        $hourlyAttempts = RateLimiter::attempt(
            $hourlyKey,
            $hourlyLimit,
            fn () => true,
            3600
        );

        if (! $hourlyAttempts) {
            return false;
        }

        $dailyAttempts = RateLimiter::attempt(
            $dailyKey,
            $dailyLimit,
            fn () => true,
            86400
        );

        return $dailyAttempts;
    }

    /**
     * Get remaining rate limit for a channel
     */
    public function getRateLimitRemaining(int $landlordId, string $channel): array
    {
        $hourlyKey = "notifications:{$landlordId}:{$channel}:hourly";
        $dailyKey = "notifications:{$landlordId}:{$channel}:daily";

        $rateLimits = $this->configRepository->getRateLimits($landlordId);
        $hourlyLimit = $rateLimits['hourly'];
        $dailyLimit = $rateLimits['daily'];

        return [
            'hourly' => [
                'remaining' => max(0, $hourlyLimit - RateLimiter::attempts($hourlyKey)),
                'limit' => $hourlyLimit,
                'resets_at' => RateLimiter::availableAt($hourlyKey),
            ],
            'daily' => [
                'remaining' => max(0, $dailyLimit - RateLimiter::attempts($dailyKey)),
                'limit' => $dailyLimit,
                'resets_at' => RateLimiter::availableAt($dailyKey),
            ],
        ];
    }

    /**
     * Reset rate limits for a landlord
     */
    public function resetRateLimits(int $landlordId, ?string $channel = null): void
    {
        $channels = $channel ? [$channel] : ['email', 'sms', 'whatsapp', 'push'];

        foreach ($channels as $ch) {
            RateLimiter::clear("notifications:{$landlordId}:{$ch}:hourly");
            RateLimiter::clear("notifications:{$landlordId}:{$ch}:daily");
        }
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
        $prioritizedChannels = $this->prioritizeChannelsWithUrgency($preferences, $allowedChannels);
        $primaryChannel = $this->findPrimaryChannel($prioritizedChannels, $preferences, $type);

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

                return false;
            }
        }

        return false;
    }
}
