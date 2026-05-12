<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\LimitsPerPage;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

class TenantNotificationController extends Controller
{
    use LimitsPerPage;

    public function index(Request $request)
    {
        $user = $request->user();

        // Phase-15 PERF-3: per_page cap.
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($this->resolvePerPage($request, default: 20));

        return NotificationResource::collection($notifications)
            ->additional([
                'meta' => [
                    'unread_count' => $user->unreadNotifications()->count(),
                ],
            ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $user = $request->user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
