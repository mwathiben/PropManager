<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendScheduledNotificationsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Phase-16 QUEUE-1: explicit timeout. This job iterates schedules
     * and dispatches per-recipient notification work — under load, the
     * 60s worker default is insufficient.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotificationSchedule $schedule,
        public int $recipientId,
        public string $subject,
        public string $message,
        public array $context = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('SendScheduledNotificationsJob: Starting', [
            'schedule_id' => $this->schedule->id,
            'recipient_id' => $this->recipientId,
            'channels' => $this->schedule->channels,
        ]);

        try {
            $recipient = User::find($this->recipientId);

            if (! $recipient) {
                Log::warning('SendScheduledNotificationsJob: Recipient not found', [
                    'recipient_id' => $this->recipientId,
                    'schedule_id' => $this->schedule->id,
                ]);

                return;
            }

            $existingNotification = Notification::where('recipient_id', $this->recipientId)
                ->where('type', $this->schedule->type)
                ->whereJsonContains('data->schedule_id', $this->schedule->id)
                ->whereIn('status', ['sent', 'delivered', 'read', 'pending'])
                ->first();

            if ($existingNotification) {
                Log::info('SendScheduledNotificationsJob: Already sent for this schedule', [
                    'schedule_id' => $this->schedule->id,
                    'recipient_id' => $this->recipientId,
                    'existing_notification_id' => $existingNotification->id,
                ]);

                return;
            }

            $contextWithSchedule = array_merge($this->context, ['schedule_id' => $this->schedule->id]);

            $notificationService->send(
                $this->recipientId,
                $this->schedule->type,
                $this->subject,
                $this->message,
                $contextWithSchedule,
                $this->schedule->landlord_id
            );

            Log::info('SendScheduledNotificationsJob: Completed', [
                'schedule_id' => $this->schedule->id,
                'recipient_id' => $this->recipientId,
            ]);
        } catch (\Exception $e) {
            Log::error('SendScheduledNotificationsJob: Failed', [
                'recipient_id' => $this->recipientId,
                'schedule_id' => $this->schedule->id,
                'error' => $e->getMessage(),
            ]);

            // HANDLE-4: re-throw so Laravel's queue retry/back-off applies.
            // Without this the job is silently marked complete and the
            // tenant simply never gets the scheduled notification.
            throw $e;
        }
    }

    /**
     * Called by the queue worker when retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendScheduledNotificationsJob: Permanently failed', [
            'recipient_id' => $this->recipientId,
            'schedule_id' => $this->schedule->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'scheduled-notification',
            'schedule:'.$this->schedule->id,
            'recipient:'.$this->recipientId,
        ];
    }
}
