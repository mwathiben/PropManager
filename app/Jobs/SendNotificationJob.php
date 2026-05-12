<?php

namespace App\Jobs;

use App\Jobs\Concerns\CarriesRequestId;
use App\Models\Notification;
use App\Services\MetricsService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldBeUnique, ShouldQueue
{
    // Phase-14 OBSERV-4: carry the HTTP request_id across the queue
    // boundary. Callers do `->withCurrentRequestId()` at dispatch.
    use CarriesRequestId, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * CONC-10: how long the unique-job lock is held in seconds.
     *
     * Bound the dedup window to 5 minutes — long enough to absorb a
     * duplicate dispatch from the existing app-level dedup window in
     * handleNewNotification(), short enough that a genuinely lost lock
     * (e.g. queue worker crash mid-job) doesn't permanently shadow real
     * traffic.
     */
    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        // For deferred notifications, the notification_id is sufficient —
        // it's already a unique row.
        if ($this->notificationId) {
            return 'def:'.$this->notificationId;
        }

        // For "new" notifications, dedup on the recipient + type + subject +
        // landlord. This replaces the uncommitted-read scan in
        // handleNewNotification with a transactional cache lock so two
        // dispatches of the same logical notification can't both pass the
        // existence check before either has written its row.
        return 'new:'.implode(':', [
            $this->landlordId ?? 0,
            $this->recipientId ?? 0,
            $this->type ?? '',
            sha1((string) $this->subject),
        ]);
    }

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

            // OBS-11: notification.dispatched is the queue-throughput
            // signal; pair it with notification.failed and the
            // notification-channel logs from OBS-4 to spot delivery
            // regressions before tenants complain.
            app(MetricsService::class)->increment(
                'notification.dispatched',
                labels: ['type' => $this->type ?? 'deferred']
            );
        } catch (\Exception $e) {
            Log::error('SendNotificationJob failed', [
                'notification_id' => $this->notificationId,
                'recipient_id' => $this->recipientId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            app(MetricsService::class)->increment(
                'notification.failed',
                labels: ['type' => $this->type ?? 'deferred']
            );

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

        if ($notification->status !== \App\Enums\NotificationStatus::Pending) {
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
        $existingNotification = Notification::where('recipient_id', $this->recipientId)
            ->where('type', $this->type)
            ->where('subject', $this->subject)
            ->where('landlord_id', $this->landlordId)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($existingNotification) {
            Log::info('SendNotificationJob: Duplicate notification detected, skipping', [
                'recipient_id' => $this->recipientId,
                'type' => $this->type,
                'existing_notification_id' => $existingNotification->id,
            ]);

            return;
        }

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
