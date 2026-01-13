<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantNotificationController extends Controller
{
    /**
     * Display the tenant's notifications page.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isTenant()) {
            abort(403, 'This page is only for tenants.');
        }

        $query = Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)
            ->orderByDesc('created_at');

        // Filter by read status
        if ($request->has('filter') && $request->filter !== 'all') {
            if ($request->filter === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->filter === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        $notifications = $query->paginate(20)->withQueryString();

        $unreadCount = Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return Inertia::render('Tenant/Notifications', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'filter' => $request->filter ?? 'all',
        ]);
    }

    /**
     * Get notifications for the dropdown (JSON API).
     * Now supports all authenticated users for invitation notifications.
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = auth()->user();

        $notifications = Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($notification) {
                $data = [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'channel' => $notification->channel,
                    'subject' => $notification->subject,
                    'message' => $notification->message,
                    'status' => $notification->status,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'time_ago' => $notification->created_at->diffForHumans(),
                    'is_invitation' => $notification->isInvitation(),
                ];

                // Add invitation-specific data for action buttons
                if ($notification->isInvitation() && $notification->data) {
                    $data['invitation_id'] = $notification->getInvitationId();
                    $data['invitation_type'] = $notification->getInvitationType();
                }

                return $data;
            });

        $unreadCount = Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $user = auth()->user();

        // Authorization check
        if ($notification->recipient_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isTenant()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'status' => 'read',
            ]);

        return response()->json(['success' => true]);
    }
}
