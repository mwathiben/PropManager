<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase-16 QUEUE-2: per-recipient unit inside a Bus::batch created by
 * SendBulkNotificationsJob. Pre-fix, the bulk job iterated recipients
 * in-band — one failure retried the whole batch. Now each recipient
 * is its own batch entry; failures are isolated and surfaced via
 * Bus::findBatch($id)->failedJobs.
 *
 * Built specifically for the bulk-fan-out path; for regular single
 * notifications, SendNotificationJob remains the canonical entry point.
 */
class PerRecipientBulkNotificationJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public int $timeout = 120;

    public function __construct(
        public readonly int $recipientId,
        public readonly string $type,
        public readonly string $subject,
        public readonly string $message,
        public readonly ?array $data,
        public readonly int $landlordId,
        public readonly array $channels = ['email', 'sms', 'whatsapp'],
    ) {}

    public function handle(NotificationService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $service->sendBulk(
            [$this->recipientId],
            $this->type,
            $this->subject,
            $this->message,
            $this->data,
            $this->landlordId,
            $this->channels,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('PerRecipientBulkNotificationJob failed', [
            'recipient_id' => $this->recipientId,
            'type' => $this->type,
            'landlord_id' => $this->landlordId,
            'batch_id' => $this->data['batch_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
