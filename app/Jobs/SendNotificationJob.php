<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $notificationId  For deferred/scheduled notifications
     * @param  int|null  $recipientId  For new notifications
     */
    public function __construct(
        public ?int $notificationId = null,
        public ?int $recipientId = null,
        public ?string $type = null,
        public ?string $subject = null,
        public ?string $message = null,
        public ?array $data = null,
        public ?int $landlordId = null
    ) {
        //
    }

    /**
     * Create job for a deferred notification.
     */
    public static function forDeferred(int $notificationId): self
    {
        return new self(notificationId: $notificationId);
    }

    /**
     * Create job for a new notification.
     */
    public static function forNew(
        int $recipientId,
        string $type,
        string $subject,
        string $message,
        ?array $data = null,
        ?int $landlordId = null
    ): self {
        return new self(
            notificationId: null,
            recipientId: $recipientId,
            type: $type,
            subject: $subject,
            message: $message,
            data: $data,
            landlordId: $landlordId
        );
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            if ($this->notificationId) {
                $this->handleDeferredNotification($notificationService);
            } else {
                $this->handleNewNotification($notificationService);
            }
        } catch (\Exception $e) {
            Log::error('SendNotificationJob failed', [
                'notification_id' => $this->notificationId,
                'recipient_id' => $this->recipientId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle sending a deferred/scheduled notification.
     */
    protected function handleDeferredNotification(NotificationService $notificationService): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification) {
            Log::warning('Deferred notification not found', [
                'notification_id' => $this->notificationId,
            ]);

            return;
        }

        if ($notification->status !== 'pending') {
            Log::info('Deferred notification already processed', [
                'notification_id' => $this->notificationId,
                'status' => $notification->status,
            ]);

            return;
        }

        $notificationService->sendDeferredNotification($notification);
    }

    /**
     * Handle sending a new notification.
     */
    protected function handleNewNotification(NotificationService $notificationService): void
    {
        $notificationService->send(
            $this->recipientId,
            $this->type,
            $this->subject,
            $this->message,
            $this->data,
            $this->landlordId
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationJob permanently failed', [
            'notification_id' => $this->notificationId,
            'recipient_id' => $this->recipientId,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
