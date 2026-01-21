<?php

namespace App\Jobs;

use App\Exceptions\Notification\ChannelSendException;
use App\Exceptions\Notification\RecipientNotFoundException;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FallbackNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Exponential backoff: 30s, 1min, 3min
     */
    public array $backoff = [30, 60, 180];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $notificationId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification) {
            Log::warning('FallbackNotificationJob: Notification not found', [
                'notification_id' => $this->notificationId,
            ]);

            return;
        }

        if ($notification->status === 'delivered' || $notification->status === 'read') {
            Log::info('FallbackNotificationJob: Notification already delivered, skipping fallback', [
                'notification_id' => $this->notificationId,
                'status' => $notification->status,
            ]);

            return;
        }

        $nextChannel = $notification->getNextFallbackChannel();

        if ($nextChannel === null) {
            $this->handleAllChannelsExhausted($notification, $notificationService);

            return;
        }

        Log::info('FallbackNotificationJob: Attempting fallback', [
            'notification_id' => $this->notificationId,
            'from_channel' => $notification->fallback_channel ?? $notification->channel,
            'to_channel' => $nextChannel,
        ]);

        try {
            $recipient = $notification->recipient;

            if (! $recipient) {
                throw new RecipientNotFoundException($notification->recipient_id, $notification->type);
            }

            $sent = $notificationService->sendViaChannel(
                $notification,
                $recipient,
                $nextChannel
            );

            if ($sent) {
                $notification->markAsSentViaFallback($nextChannel);

                Log::info('FallbackNotificationJob: Successfully sent via fallback', [
                    'notification_id' => $this->notificationId,
                    'channel' => $nextChannel,
                ]);
            } else {
                $notification->update([
                    'fallback_channel' => $nextChannel,
                    'status' => 'failed',
                ]);
                $notification->incrementRetryCount();

                throw new ChannelSendException($nextChannel);
            }
        } catch (\Exception $e) {
            Log::error('FallbackNotificationJob: Failed to send via fallback', [
                'notification_id' => $this->notificationId,
                'channel' => $nextChannel,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle when all channels have been exhausted.
     */
    private function handleAllChannelsExhausted(
        Notification $notification,
        NotificationService $notificationService
    ): void {
        Log::warning('FallbackNotificationJob: All channels exhausted', [
            'notification_id' => $this->notificationId,
            'recipient_id' => $notification->recipient_id,
            'type' => $notification->type,
        ]);

        $notification->update([
            'status' => 'failed',
            'error_message' => 'All notification channels exhausted',
        ]);

        $notificationService->notifyLandlordUnreachable($notification);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FallbackNotificationJob permanently failed', [
            'notification_id' => $this->notificationId,
            'error' => $exception->getMessage(),
        ]);

        $notification = Notification::find($this->notificationId);
        if ($notification) {
            $notification->incrementRetryCount();
        }
    }
}
