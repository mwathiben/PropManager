<?php

namespace App\Jobs;

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
        try {
            $recipient = User::find($this->recipientId);

            if (! $recipient) {
                Log::warning('Scheduled notification recipient not found', [
                    'recipient_id' => $this->recipientId,
                    'schedule_id' => $this->schedule->id,
                ]);

                return;
            }

            // Send via all configured channels for this schedule
            foreach ($this->schedule->channels as $channel) {
                try {
                    $notificationService->send(
                        $this->recipientId,
                        $this->schedule->type,
                        $this->subject,
                        $this->message,
                        $this->context,
                        $this->schedule->landlord_id
                    );
                } catch (\Exception $e) {
                    Log::error('Scheduled notification channel failed', [
                        'channel' => $channel,
                        'recipient_id' => $this->recipientId,
                        'schedule_id' => $this->schedule->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Scheduled notification job failed', [
                'recipient_id' => $this->recipientId,
                'schedule_id' => $this->schedule->id,
                'error' => $e->getMessage(),
            ]);
        }
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
