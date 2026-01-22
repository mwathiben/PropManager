<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    public string $batchId;

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
        public array $channels = ['email', 'sms', 'whatsapp'],
        ?string $batchId = null
    ) {
        $this->batchId = $batchId ?? Str::uuid()->toString();
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('SendBulkNotificationsJob: Starting batch', [
            'batch_id' => $this->batchId,
            'landlord_id' => $this->landlordId,
            'type' => $this->type,
            'recipient_count' => count($this->recipientIds),
        ]);

        try {
            $alreadySentTo = Notification::where('type', $this->type)
                ->where('landlord_id', $this->landlordId)
                ->whereIn('recipient_id', $this->recipientIds)
                ->whereJsonContains('data->batch_id', $this->batchId)
                ->pluck('recipient_id')
                ->toArray();

            $remainingRecipients = array_values(array_diff($this->recipientIds, $alreadySentTo));

            if (empty($remainingRecipients)) {
                Log::info('SendBulkNotificationsJob: All recipients already notified', [
                    'batch_id' => $this->batchId,
                    'skipped_count' => count($alreadySentTo),
                ]);

                return;
            }

            $dataWithBatch = array_merge($this->data ?? [], ['batch_id' => $this->batchId]);

            $results = $notificationService->sendBulk(
                $remainingRecipients,
                $this->type,
                $this->subject,
                $this->message,
                $dataWithBatch,
                $this->landlordId,
                $this->channels
            );

            Log::info('SendBulkNotificationsJob: Completed', [
                'batch_id' => $this->batchId,
                'landlord_id' => $this->landlordId,
                'type' => $this->type,
                'results' => $results,
                'skipped_recipients' => count($alreadySentTo),
            ]);
        } catch (\Exception $e) {
            Log::error('SendBulkNotificationsJob: Failed', [
                'batch_id' => $this->batchId,
                'landlord_id' => $this->landlordId,
                'type' => $this->type,
                'recipient_count' => count($this->recipientIds),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendBulkNotificationsJob permanently failed', [
            'batch_id' => $this->batchId,
            'landlord_id' => $this->landlordId,
            'type' => $this->type,
            'recipient_count' => count($this->recipientIds),
            'error' => $exception->getMessage(),
        ]);
    }
}
