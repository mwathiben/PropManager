<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-104 OWNER-REMITTANCE-NOTIFY: the property owner's notifications surface (payout
 * remittances + statement notices). Scoped strictly to the authed owner's own
 * recipient_id — never a route param; an owner can only ever see/read their own.
 */
class OwnerPortalNotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->isOwner(), 403);

        $query = Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('filter') && $request->filter !== 'all') {
            $request->filter === 'unread'
                ? $query->whereNull('read_at')
                : $query->whereNotNull('read_at');
        }

        return Inertia::render('Owner/Notifications', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'unreadCount' => Notification::withoutGlobalScope('landlord')
                ->where('recipient_id', $user->id)
                ->whereNull('read_at')
                ->count(),
            'filter' => $request->filter ?? 'all',
        ]);
    }

    public function markAsRead(Notification $notification): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->isOwner(), 403);

        // Can only mark one's OWN notification (never another user's by id).
        if ((int) $notification->recipient_id !== (int) $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->isOwner(), 403);

        Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'status' => 'read']);

        return response()->json(['success' => true]);
    }
}
