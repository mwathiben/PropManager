<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkNotificationsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $recipientIds,
        public string $type,
        public string $subject,
        public string $message,
        public ?array $data,
        public int $landlordId,
        public array $channels = ['email', 'sms', 'whatsapp']
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $results = $notificationService->sendBulk(
                $this->recipientIds,
                $this->type,
                $this->subject,
                $this->message,
                $this->data,
                $this->landlordId,
                $this->channels
            );

            Log::info('Bulk notifications sent', [
                'landlord_id' => $this->landlordId,
                'type' => $this->type,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('SendBulkNotificationsJob failed', [
                'landlord_id' => $this->landlordId,
                'type' => $this->type,
                'recipient_count' => count($this->recipientIds),
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
        Log::error('SendBulkNotificationsJob permanently failed', [
            'landlord_id' => $this->landlordId,
            'type' => $this->type,
            'recipient_count' => count($this->recipientIds),
            'error' => $exception->getMessage(),
        ]);
    }
}
