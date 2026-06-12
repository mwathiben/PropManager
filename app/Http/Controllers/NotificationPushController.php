<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\SubscribePushRequest;
use App\Http\Requests\Notification\UnsubscribePushRequest;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;

/**
 * Web-push subscription + VAPID key endpoints, split out of
 * NotificationsController (M2 decomposition). Verbatim move; routes keep
 * their original names (notifications.push.*, notifications.settings.vapid)
 * and point here. Behaviour is locked by Phase37PushSubscriptionTest.
 */
class NotificationPushController extends Controller
{
    public function __construct(
        protected PushNotificationService $pushService,
    ) {}

    /**
     * Generate VAPID keys for push notifications
     */
    public function generateVapidKeys(): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $keys = $this->pushService->generateVapidKeys();
        $this->pushService->saveVapidKeys($landlordId, $keys);

        return response()->json([
            'success' => true,
            'public_key' => $keys['public'],
        ]);
    }

    /**
     * Subscribe to push notifications
     */
    public function subscribePush(SubscribePushRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = auth()->user();

        $subscription = $this->pushService->subscribe($user->id, $validated);

        return response()->json([
            'success' => true,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribePush(UnsubscribePushRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->pushService->unsubscribe($validated['endpoint']);

        return response()->json([
            'success' => $result,
        ]);
    }

    /**
     * Get VAPID public key
     */
    public function getVapidPublicKey(): JsonResponse
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $publicKey = $this->pushService->getPublicKey($landlordId);

        return response()->json([
            'public_key' => $publicKey,
        ]);
    }
}
