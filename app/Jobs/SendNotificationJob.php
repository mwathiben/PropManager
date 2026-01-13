<?php

namespace App\Jobs;

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
     */
    public function __construct(
        public int $recipientId,
        public string $type,
        public string $subject,
        public string $message,
        public ?array $data = null,
        public ?int $landlordId = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $notificationService->send(
                $this->recipientId,
                $this->type,
                $this->subject,
                $this->message,
                $this->data,
                $this->landlordId
            );
        } catch (\Exception $e) {
            Log::error('SendNotificationJob failed', [
                'recipient_id' => $this->recipientId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationJob permanently failed', [
            'recipient_id' => $this->recipientId,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
