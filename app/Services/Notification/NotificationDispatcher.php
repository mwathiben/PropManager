<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    /**
     * Dispatch a notification via its channel with error handling.
     * Returns status string: 'sent', 'failed'.
     */
    public function dispatch(
        Notification $notification,
        User $recipient,
        callable $sendCallback
    ): string {
        try {
            $sent = $sendCallback($notification, $recipient);

            return $sent ? 'sent' : 'failed';
        } catch (\Exception $e) {
            $this->handleFailure($notification, $e);

            return 'failed';
        }
    }

    /**
     * Handle notification failure by marking it failed and logging.
     */
    private function handleFailure(Notification $notification, \Exception $e): void
    {
        $notification->markAsFailed($e->getMessage());

        Log::error("Notification failed via {$notification->channel}", [
            'error' => $e->getMessage(),
            'notification_id' => $notification->id,
        ]);
    }
}
