<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\TenantMessage;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantMessageService
{
    protected const KEYWORD_PATTERNS = [
        TenantMessage::ACTION_YES => '/^(yes|yeah|ok|okay|confirm|accept|approved?)\s*$/i',
        TenantMessage::ACTION_NO => '/^(no|nope|decline|reject|cancel)\s*$/i',
        TenantMessage::ACTION_HELP => '/\b(help|support|assist|question)\b/i',
        TenantMessage::ACTION_ISSUE => '/\b(broken|problem|issue|repair|fix|leak|water|electricity|plumbing|not working|doesn\'t work|stopped working)\b/i',
        TenantMessage::ACTION_PAYMENT => '/\b(pay|payment|mpesa|paybill|invoice)\b/i',
    ];

    public function processInbound(array $payload): TenantMessage
    {
        $messageSid = $payload['MessageSid'] ?? null;
        $from = $this->normalizePhone($payload['From'] ?? '');
        $body = $payload['Body'] ?? '';
        $numMedia = (int) ($payload['NumMedia'] ?? 0);
        $source = str_contains($payload['From'] ?? '', 'whatsapp')
            ? TenantMessage::SOURCE_WHATSAPP
            : TenantMessage::SOURCE_SMS;

        Log::channel('whatsapp')->info('Processing inbound message', [
            'message_sid' => $messageSid,
            'from' => $from,
            'source' => $source,
            'body_length' => strlen($body),
            'num_media' => $numMedia,
        ]);

        $tenant = $this->findTenantByPhone($from);
        $landlordId = $tenant?->landlord_id;

        if (! $landlordId && ! $tenant) {
            Log::channel('whatsapp')->warning('Unknown sender phone number', [
                'from' => $from,
                'message_sid' => $messageSid,
            ]);

            $landlordId = $this->findAnyLandlordByPhone($from);
        }

        if (! $landlordId) {
            Log::channel('whatsapp')->error('Cannot determine landlord for inbound message', [
                'from' => $from,
            ]);

            throw new \RuntimeException('Cannot determine landlord for message');
        }

        $mediaUrls = $this->extractMediaUrls($payload, $numMedia);

        return DB::transaction(function () use ($messageSid, $from, $body, $mediaUrls, $source, $payload, $tenant, $landlordId) {
            $existingMessage = TenantMessage::where('twilio_message_sid', $messageSid)
                ->lockForUpdate()
                ->first();

            if ($existingMessage) {
                Log::channel('whatsapp')->info('Duplicate message ignored', [
                    'message_sid' => $messageSid,
                ]);

                return $existingMessage;
            }

            $notification = $tenant ? $this->findOriginalNotification($tenant, $from) : null;

            $message = TenantMessage::create([
                'landlord_id' => $landlordId,
                'user_id' => $tenant?->id,
                'notification_id' => $notification?->id,
                'twilio_message_sid' => $messageSid,
                'from_number' => $from,
                'body' => $body,
                'media_urls' => $mediaUrls,
                'source' => $source,
                'status' => TenantMessage::STATUS_RECEIVED,
                'metadata' => $this->extractMetadata($payload),
            ]);

            $actionType = $this->detectActionKeyword($body);

            if ($actionType) {
                $this->executeAction($message, $actionType, $tenant);
            }

            $message->markAsProcessed($actionType);

            $this->notifyLandlord($message, $tenant);

            Log::channel('whatsapp')->info('Inbound message processed', [
                'message_id' => $message->id,
                'tenant_id' => $tenant?->id,
                'action_type' => $actionType,
                'is_reply' => $message->isReply(),
            ]);

            return $message;
        });
    }

    public function findTenantByPhone(string $phone): ?User
    {
        $preference = NotificationPreference::where('whatsapp_number', $phone)->first();

        return $preference?->user;
    }

    protected function findAnyLandlordByPhone(string $phone): ?int
    {
        $preference = NotificationPreference::where('whatsapp_number', $phone)->first();

        return $preference?->landlord_id;
    }

    public function findOriginalNotification(User $tenant, string $phone): ?Notification
    {
        return Notification::where('recipient_id', $tenant->id)
            ->where('channel', Notification::CHANNEL_WHATSAPP)
            ->where('status', '!=', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function detectActionKeyword(string $body): ?string
    {
        $trimmedBody = trim($body);

        foreach (self::KEYWORD_PATTERNS as $action => $pattern) {
            if (preg_match($pattern, $trimmedBody)) {
                return $action;
            }
        }

        return null;
    }

    public function executeAction(TenantMessage $message, string $actionType, ?User $tenant): void
    {
        Log::channel('whatsapp')->info('Executing action for message', [
            'message_id' => $message->id,
            'action_type' => $actionType,
        ]);

        match ($actionType) {
            TenantMessage::ACTION_HELP, TenantMessage::ACTION_ISSUE => $this->createTicket($message, $tenant, $actionType),
            TenantMessage::ACTION_YES => $this->handleConfirmation($message, $tenant),
            TenantMessage::ACTION_NO => $this->handleRejection($message, $tenant),
            TenantMessage::ACTION_PAYMENT => $this->handlePaymentRequest($message, $tenant),
            default => null,
        };
    }

    protected function createTicket(TenantMessage $message, ?User $tenant, string $actionType): void
    {
        if (! $tenant) {
            Log::channel('whatsapp')->warning('Cannot create ticket without tenant', [
                'message_id' => $message->id,
            ]);

            return;
        }

        $lease = $tenant->leases()->where('is_active', true)->first();

        $category = $actionType === TenantMessage::ACTION_ISSUE ? 'issue' : 'complaint';
        $subcategory = $this->detectSubcategory($message->body);

        $ticket = Ticket::create([
            'landlord_id' => $message->landlord_id,
            'building_id' => $lease?->unit?->building_id,
            'unit_id' => $lease?->unit_id,
            'reporter_id' => $tenant->id,
            'category' => $category,
            'subcategory' => $subcategory,
            'title' => 'WhatsApp: '.substr($message->body, 0, 50).(strlen($message->body) > 50 ? '...' : ''),
            'description' => $message->body,
            'priority' => 'medium',
            'status' => 'open',
            'location' => $lease?->unit?->name,
        ]);

        $message->linkToTicket($ticket);

        $ticket->logActivity('created', null, null, 'Ticket created from WhatsApp message');

        Log::channel('whatsapp')->info('Ticket created from WhatsApp message', [
            'message_id' => $message->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    protected function detectSubcategory(string $body): string
    {
        $subcategoryPatterns = [
            'plumbing' => '/\b(plumb|pipe|drain|tap|faucet|sink|toilet|shower|leak)\b/i',
            'electrical' => '/\b(electric|power|socket|switch|light|bulb|wire|outlet)\b/i',
            'water_supply' => '/\b(water|tank|pump|supply)\b/i',
            'structural' => '/\b(wall|floor|ceiling|door|window|roof|crack)\b/i',
            'appliances' => '/\b(fridge|stove|oven|washer|dryer|appliance|heater)\b/i',
            'pest_control' => '/\b(pest|insect|bug|rat|mice|cockroach|ant)\b/i',
        ];

        foreach ($subcategoryPatterns as $subcategory => $pattern) {
            if (preg_match($pattern, $body)) {
                return $subcategory;
            }
        }

        return 'other';
    }

    protected function handleConfirmation(TenantMessage $message, ?User $tenant): void
    {
        if (! $message->isReply() || ! $message->notification) {
            return;
        }

        $notificationType = $message->notification->type ?? null;

        if ($notificationType === 'lease_renewal') {
            Log::channel('whatsapp')->info('Lease renewal confirmation received', [
                'message_id' => $message->id,
                'notification_id' => $message->notification_id,
            ]);
        }
    }

    protected function handleRejection(TenantMessage $message, ?User $tenant): void
    {
        if (! $message->isReply() || ! $message->notification) {
            return;
        }

        $notificationType = $message->notification->type ?? null;

        if ($notificationType === 'lease_renewal') {
            Log::channel('whatsapp')->info('Lease renewal rejection received', [
                'message_id' => $message->id,
                'notification_id' => $message->notification_id,
            ]);
        }
    }

    protected function handlePaymentRequest(TenantMessage $message, ?User $tenant): void
    {
        Log::channel('whatsapp')->info('Payment request received', [
            'message_id' => $message->id,
            'tenant_id' => $tenant?->id,
        ]);
    }

    public function notifyLandlord(TenantMessage $message, ?User $tenant): void
    {
        $landlord = $message->landlord;

        if (! $landlord) {
            return;
        }

        $tenantName = $tenant?->name ?? 'Unknown sender';
        $previewBody = substr($message->body, 0, 100).(strlen($message->body) > 100 ? '...' : '');

        SendNotificationJob::dispatch(
            $landlord->id,
            'general',
            "New WhatsApp message from {$tenantName}",
            "Message: {$previewBody}",
            [
                'tenant_message_id' => $message->id,
                'from_number' => $message->from_number,
                'has_ticket' => $message->hasTicket(),
                'ticket_id' => $message->ticket_id,
            ],
            $message->landlord_id
        );
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/^whatsapp:/', '', $phone);

        return trim($phone);
    }

    protected function extractMediaUrls(array $payload, int $numMedia): array
    {
        $urls = [];

        for ($i = 0; $i < $numMedia; $i++) {
            if (isset($payload["MediaUrl{$i}"])) {
                $urls[] = [
                    'url' => $payload["MediaUrl{$i}"],
                    'content_type' => $payload["MediaContentType{$i}"] ?? null,
                ];
            }
        }

        return $urls;
    }

    protected function extractMetadata(array $payload): array
    {
        return [
            'account_sid' => $payload['AccountSid'] ?? null,
            'to' => $payload['To'] ?? null,
            'sms_status' => $payload['SmsStatus'] ?? null,
            'profile_name' => $payload['ProfileName'] ?? null,
        ];
    }
}
