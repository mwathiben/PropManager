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
    protected TenantMessageAnalyzer $analyzer;

    public function __construct(?TenantMessageAnalyzer $analyzer = null)
    {
        $this->analyzer = $analyzer ?? new TenantMessageAnalyzer;
    }

    public function processInbound(array $payload): TenantMessage
    {
        $from = $this->normalizePhone($payload['From'] ?? '');
        $numMedia = (int) ($payload['NumMedia'] ?? 0);

        $ctx = [
            'message_sid' => $payload['MessageSid'] ?? null,
            'from' => $from,
            'body' => $payload['Body'] ?? '',
            'num_media' => $numMedia,
            'source' => $this->resolveSource($payload),
            'media_urls' => $this->extractMediaUrls($payload, $numMedia),
            'metadata' => $this->extractMetadata($payload),
        ];

        Log::channel('whatsapp')->info('Processing inbound message', [
            'message_sid' => $ctx['message_sid'],
            'from' => $ctx['from'],
            'source' => $ctx['source'],
            'body_length' => strlen($ctx['body']),
            'num_media' => $ctx['num_media'],
        ]);

        $tenant = $this->findTenantByPhone($from);
        $landlordId = $this->resolveLandlordId($tenant, $from, $ctx['message_sid']);

        return DB::transaction(function () use ($ctx, $tenant, $landlordId) {
            return $this->persistInboundMessage($ctx, $tenant, $landlordId);
        });
    }

    private function resolveSource(array $payload): string
    {
        return str_contains($payload['From'] ?? '', 'whatsapp')
            ? TenantMessage::SOURCE_WHATSAPP
            : TenantMessage::SOURCE_SMS;
    }

    private function resolveLandlordId(?User $tenant, string $from, ?string $messageSid): int
    {
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

        return $landlordId;
    }

    private function persistInboundMessage(array $ctx, ?User $tenant, int $landlordId): TenantMessage
    {
        $messageSid = $ctx['message_sid'];

        $existingMessage = TenantMessage::where('twilio_message_sid', $messageSid)
            ->lockForUpdate()
            ->first();

        if ($existingMessage) {
            Log::channel('whatsapp')->info('Duplicate message ignored', [
                'message_sid' => $messageSid,
            ]);

            return $existingMessage;
        }

        $notification = $tenant ? $this->findOriginalNotification($tenant, $ctx['from']) : null;

        $message = TenantMessage::create([
            'landlord_id' => $landlordId,
            'user_id' => $tenant?->id,
            'notification_id' => $notification?->id,
            'twilio_message_sid' => $messageSid,
            'from_number' => $ctx['from'],
            'body' => $ctx['body'],
            'media_urls' => $ctx['media_urls'],
            'source' => $ctx['source'],
            'status' => TenantMessage::STATUS_RECEIVED,
            'metadata' => $ctx['metadata'],
        ]);

        $actionType = $this->detectActionKeyword($ctx['body']);

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
        return $this->analyzer->detectActionKeyword($body);
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

        $category = $this->analyzer->determineCategory($actionType);
        $subcategory = $this->analyzer->detectSubcategory($message->body);
        $priority = $this->analyzer->determinePriority($message->body);

        $ticket = Ticket::create([
            'landlord_id' => $message->landlord_id,
            'building_id' => $lease?->unit?->building_id,
            'unit_id' => $lease?->unit_id,
            'reporter_id' => $tenant->id,
            'category' => $category,
            'subcategory' => $subcategory,
            'title' => 'WhatsApp: '.substr($message->body, 0, 50).(strlen($message->body) > 50 ? '...' : ''),
            'description' => $message->body,
            'priority' => $priority,
            'status' => 'open',
            'location' => $lease?->unit?->name,
        ]);

        $message->linkToTicket($ticket);

        $ticket->logActivity('created', null, null, 'Ticket created from WhatsApp message');

        Log::channel('whatsapp')->info('Ticket created from WhatsApp message', [
            'message_id' => $message->id,
            'ticket_id' => $ticket->id,
            'priority' => $priority,
        ]);

        $this->notifyCaretakerOfTicket($ticket, $message, $tenant);
        $this->confirmTicketToTenant($ticket, $tenant, $message->source);
    }

    protected function detectSubcategory(string $body): string
    {
        return $this->analyzer->detectSubcategory($body);
    }

    protected function notifyCaretakerOfTicket(Ticket $ticket, TenantMessage $message, User $tenant): void
    {
        $building = $ticket->building;
        $caretaker = $building?->caretaker;

        if (! $caretaker) {
            Log::channel('whatsapp')->info('No caretaker assigned to building, skipping notification', [
                'ticket_id' => $ticket->id,
                'building_id' => $building?->id,
            ]);

            return;
        }

        $unitName = $ticket->unit?->name ?? 'Unknown unit';
        $previewBody = substr($message->body, 0, 100).(strlen($message->body) > 100 ? '...' : '');

        // CONC-4: handleInbound wraps this call in DB::transaction; afterCommit
        // ensures the queue worker sees a committed notification context.
        dispatch(SendNotificationJob::forNew(
            $caretaker->id,
            'maintenance_notice',
            "New {$ticket->priority} priority issue from {$tenant->name}",
            "Unit {$unitName}: {$previewBody}",
            [
                'ticket_id' => $ticket->id,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'unit_name' => $unitName,
                'building_name' => $building->name ?? null,
                'category' => $ticket->category,
                'subcategory' => $ticket->subcategory,
                'priority' => $ticket->priority,
            ],
            $ticket->landlord_id
        ))->afterCommit();

        Log::channel('whatsapp')->info('Caretaker notified of new ticket', [
            'ticket_id' => $ticket->id,
            'caretaker_id' => $caretaker->id,
        ]);
    }

    protected function confirmTicketToTenant(Ticket $ticket, User $tenant, string $source): void
    {
        $channel = $source === TenantMessage::SOURCE_WHATSAPP
            ? Notification::CHANNEL_WHATSAPP
            : Notification::CHANNEL_SMS;

        $issueSummary = substr($ticket->title, 0, 30);
        if (strlen($ticket->title) > 30) {
            $issueSummary .= '...';
        }

        dispatch(SendNotificationJob::forNew(
            $tenant->id,
            'general',
            'Your issue has been logged',
            "Hi {$tenant->name}, your issue has been logged as Ticket #{$ticket->id}. We'll update you on progress. Reference: {$issueSummary}",
            [
                'ticket_id' => $ticket->id,
                'issue_summary' => $issueSummary,
                'preferred_channel' => $channel,
            ],
            $ticket->landlord_id
        ))->afterCommit();

        Log::channel('whatsapp')->info('Tenant confirmation sent for ticket', [
            'ticket_id' => $ticket->id,
            'tenant_id' => $tenant->id,
            'channel' => $channel,
        ]);
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

        dispatch(SendNotificationJob::forNew(
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
        ))->afterCommit();
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
