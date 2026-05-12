<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendBulkNotificationsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Phase-16 QUEUE-2: this job is now a thin dispatcher that builds a
     * Bus::batch of per-recipient PerRecipientBulkNotificationJob
     * instances. Pre-fix, one failed recipient in a 200-row batch
     * caused the entire batch to retry, double-sending to the 199
     * successful recipients on retry. With Bus::batch, per-recipient
     * failures are isolated and visible in `Bus::findBatch($id)`.
     *
     * Tries = 1 because the per-recipient jobs each have their own
     * retry policy. The dispatcher itself is idempotent (dedup via
     * batch_id stamped into Notification.data) so retrying the
     * dispatcher would only re-create the batch for already-deduped
     * recipients — wasteful but not incorrect.
     */
    public int $tries = 1;

    /**
     * Phase-16 QUEUE-1: timeout the dispatcher itself. The work it does
     * (querying for already-sent recipients, building the batch) is
     * O(N) over recipientIds — for very large batches we want headroom
     * over the 60s default but not unbounded.
     */
    public int $timeout = 600;

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
    public function handle(): void
    {
        Log::info('SendBulkNotificationsJob: Starting batch', [
            'batch_id' => $this->batchId,
            'landlord_id' => $this->landlordId,
            'type' => $this->type,
            'recipient_count' => count($this->recipientIds),
        ]);

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

        $perRecipientJobs = array_map(
            fn (int $recipientId) => new PerRecipientBulkNotificationJob(
                recipientId: $recipientId,
                type: $this->type,
                subject: $this->subject,
                message: $this->message,
                data: $dataWithBatch,
                landlordId: $this->landlordId,
                channels: $this->channels,
            ),
            $remainingRecipients,
        );

        $batchId = $this->batchId;
        $landlordId = $this->landlordId;
        $type = $this->type;
        $totalRecipients = count($remainingRecipients);
        $skippedCount = count($alreadySentTo);

        Bus::batch($perRecipientJobs)
            ->name("bulk-notifications:{$this->batchId}")
            ->allowFailures()
            ->then(function (Batch $batch) use ($batchId, $landlordId, $type, $totalRecipients, $skippedCount) {
                Log::info('SendBulkNotificationsJob: batch finished', [
                    'batch_id' => $batchId,
                    'bus_batch_id' => $batch->id,
                    'landlord_id' => $landlordId,
                    'type' => $type,
                    'processed' => $batch->processedJobs(),
                    'failed' => $batch->failedJobs,
                    'total' => $totalRecipients,
                    'deduped' => $skippedCount,
                ]);
            })
            ->dispatch();
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendBulkNotificationsJob dispatcher failed', [
            'batch_id' => $this->batchId,
            'landlord_id' => $this->landlordId,
            'type' => $this->type,
            'recipient_count' => count($this->recipientIds),
            'error' => $exception->getMessage(),
        ]);
    }
}
