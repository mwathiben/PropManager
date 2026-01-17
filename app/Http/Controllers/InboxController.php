<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\TenantMessage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InboxController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function index(Request $request)
    {
        $query = TenantMessage::with(['user.lease.unit.building.property', 'notification', 'ticket'])
            ->latest();

        if ($request->filled('status')) {
            if ($request->status === 'unread') {
                $query->where('status', TenantMessage::STATUS_RECEIVED);
            } elseif ($request->status === 'processed') {
                $query->whereIn('status', [
                    TenantMessage::STATUS_PROCESSED,
                    TenantMessage::STATUS_ACTION_TAKEN,
                ]);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                    ->orWhere('from_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $messages = $query->paginate(20)->through(function ($message) {
            $unit = $message->user?->lease?->unit;

            return [
                'id' => $message->id,
                'tenant_name' => $message->user?->name ?? 'Unknown',
                'tenant_id' => $message->user_id,
                'from_number' => $message->from_number,
                'body' => $message->body,
                'body_preview' => strlen($message->body) > 100
                    ? substr($message->body, 0, 100).'...'
                    : $message->body,
                'source' => $message->source,
                'status' => $message->status,
                'action_type' => $message->action_type,
                'is_reply' => $message->isReply(),
                'original_notification' => $message->notification ? [
                    'subject' => $message->notification->subject,
                    'type' => $message->notification->type,
                ] : null,
                'has_ticket' => $message->hasTicket(),
                'ticket_id' => $message->ticket_id,
                'unit_name' => $unit ? $unit->name : null,
                'building_name' => $unit?->building?->name,
                'property_name' => $unit?->building?->property?->name,
                'media_count' => count($message->media_urls ?? []),
                'created_at' => $message->created_at->diffForHumans(),
                'created_at_full' => $message->created_at->format('M d, Y H:i'),
            ];
        });

        $unreadCount = TenantMessage::where('status', TenantMessage::STATUS_RECEIVED)->count();

        return Inertia::render('Inbox/Index', [
            'messages' => $messages,
            'unreadCount' => $unreadCount,
            'filters' => [
                'status' => $request->status ?? 'all',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function show(TenantMessage $message)
    {
        $message->load(['user.lease.unit.building.property', 'notification', 'ticket']);

        $unit = $message->user?->lease?->unit;

        return Inertia::render('Inbox/Show', [
            'message' => [
                'id' => $message->id,
                'tenant_name' => $message->user?->name ?? 'Unknown',
                'tenant_email' => $message->user?->email,
                'tenant_id' => $message->user_id,
                'from_number' => $message->from_number,
                'body' => $message->body,
                'source' => $message->source,
                'status' => $message->status,
                'action_type' => $message->action_type,
                'is_reply' => $message->isReply(),
                'original_notification' => $message->notification ? [
                    'id' => $message->notification->id,
                    'subject' => $message->notification->subject,
                    'message' => $message->notification->message,
                    'type' => $message->notification->type,
                    'created_at' => $message->notification->created_at->format('M d, Y H:i'),
                ] : null,
                'has_ticket' => $message->hasTicket(),
                'ticket' => $message->ticket ? [
                    'id' => $message->ticket->id,
                    'subject' => $message->ticket->subject,
                    'status' => $message->ticket->status,
                ] : null,
                'unit_name' => $unit?->name,
                'building_name' => $unit?->building?->name,
                'property_name' => $unit?->building?->property?->name,
                'media_urls' => $message->media_urls ?? [],
                'created_at' => $message->created_at->format('M d, Y H:i'),
                'metadata' => $message->metadata,
            ],
        ]);
    }

    public function reply(Request $request, TenantMessage $message)
    {
        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $tenant = $message->user;

        if (! $tenant) {
            return back()->with('error', 'Cannot reply: tenant not found.');
        }

        $channel = $message->source === TenantMessage::SOURCE_WHATSAPP
            ? Notification::CHANNEL_WHATSAPP
            : Notification::CHANNEL_SMS;

        $notification = Notification::create([
            'landlord_id' => auth()->id(),
            'recipient_id' => $tenant->id,
            'type' => Notification::TYPE_GENERAL,
            'channel' => $channel,
            'subject' => 'Reply from landlord',
            'message' => $request->body,
            'urgency' => 'informational',
            'data' => [
                'reply_to_message_id' => $message->id,
            ],
        ]);

        $sent = $this->notificationService->sendViaChannel($notification, $tenant);

        if ($sent) {
            $message->markAsProcessed();

            return back()->with('success', 'Reply sent successfully via '.ucfirst($message->source).'.');
        }

        return back()->with('error', 'Failed to send reply. Please try again.');
    }

    public function markAsRead(TenantMessage $message)
    {
        if ($message->status === TenantMessage::STATUS_RECEIVED) {
            $message->update(['status' => TenantMessage::STATUS_PROCESSED]);
        }

        return back()->with('success', 'Message marked as read.');
    }

    public function markAllAsRead()
    {
        TenantMessage::where('status', TenantMessage::STATUS_RECEIVED)
            ->update(['status' => TenantMessage::STATUS_PROCESSED]);

        return back()->with('success', 'All messages marked as read.');
    }
}
