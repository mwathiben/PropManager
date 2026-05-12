<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Jobs\GenerateInvoicePdf;
use App\Jobs\PerRecipientBulkNotificationJob;
use App\Jobs\SendBulkNotificationsJob;
use App\Jobs\SendScheduledNotificationsJob;
use App\Jobs\WarmFinanceCacheJob;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Phase-16 Phase 2 coverage:
 *   QUEUE-1: every long-running job declares an explicit $timeout
 *   QUEUE-2: SendBulkNotificationsJob dispatches a Bus::batch per recipient
 *   QUEUE-3: HealthCheckController checks every configured queue
 */
class Phase16QueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_running_jobs_declare_explicit_timeout(): void
    {
        // QUEUE-1. The four jobs that pre-Phase-16 lacked $timeout and were
        // vulnerable to the 60s worker default.
        $jobs = [
            GenerateInvoicePdf::class,
            WarmFinanceCacheJob::class,
            SendBulkNotificationsJob::class,
            SendScheduledNotificationsJob::class,
        ];

        foreach ($jobs as $jobClass) {
            $reflection = new \ReflectionClass($jobClass);
            $this->assertTrue(
                $reflection->hasProperty('timeout'),
                "{$jobClass} must declare an explicit \$timeout (Phase-16 QUEUE-1)",
            );

            $property = $reflection->getProperty('timeout');
            $defaultValue = $property->getDefaultValue();
            $this->assertGreaterThanOrEqual(
                120,
                $defaultValue,
                "{$jobClass} \$timeout ({$defaultValue}s) must exceed the default 60s worker timeout",
            );
        }
    }

    public function test_send_bulk_dispatches_per_recipient_batch(): void
    {
        // QUEUE-2: Pre-fix the bulk job iterated N recipients in-band so
        // one failure replayed the entire batch. Now each recipient is
        // its own batch member.
        Bus::fake();

        $job = new SendBulkNotificationsJob(
            recipientIds: [10, 20, 30, 40, 50],
            type: 'general',
            subject: 'Test',
            message: 'Hello',
            data: null,
            landlordId: 1,
            channels: ['email'],
        );

        $job->handle();

        Bus::assertBatched(function (PendingBatch $batch) {
            // 5 recipients → 5 per-recipient jobs in the batch.
            return $batch->jobs->count() === 5
                && $batch->jobs->every(fn ($j) => $j instanceof PerRecipientBulkNotificationJob);
        });
    }

    public function test_send_bulk_is_idempotent_via_batch_id_dedup(): void
    {
        // Re-dispatching the same logical batch with a stable batch_id
        // must not re-fan-out to recipients who already have a
        // Notification row stamped with that batch_id. Pre-fix this was
        // the only safety property for retries of the in-band job.
        Bus::fake();

        $landlord = User::factory()->create(['role' => 'landlord']);
        $r1 = User::factory()->create();
        $r2 = User::factory()->create();
        $r3 = User::factory()->create();

        $batchId = 'bulk-test-1';
        $job = new SendBulkNotificationsJob(
            recipientIds: [$r1->id, $r2->id, $r3->id],
            type: 'general',
            subject: 'Test',
            message: 'Hello',
            data: null,
            landlordId: $landlord->id,
            channels: ['email'],
            batchId: $batchId,
        );

        // Pre-stamp two of the three recipients as already-sent.
        \DB::table('notifications')->insert([
            [
                'landlord_id' => $landlord->id,
                'recipient_id' => $r1->id,
                'type' => 'general',
                'channel' => 'email',
                'subject' => 'Test',
                'message' => 'Hello',
                'data' => json_encode(['batch_id' => $batchId]),
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'landlord_id' => $landlord->id,
                'recipient_id' => $r2->id,
                'type' => 'general',
                'channel' => 'email',
                'subject' => 'Test',
                'message' => 'Hello',
                'data' => json_encode(['batch_id' => $batchId]),
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $job->handle();

        // Only the third recipient was remaining, so the batch must contain
        // exactly 1 per-recipient job.
        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 1);
    }

    public function test_health_check_iterates_configured_queues(): void
    {
        // QUEUE-3: pre-fix Queue::size() with no argument sampled only
        // the default queue. Now config('queue.health.queues') drives
        // the iteration, and the response includes a per-queue breakdown.
        // The endpoint may return 503 if external API checks fail in CI;
        // we only care about the queue-check shape here.
        config()->set('queue.health.queues', ['default', 'notifications', 'payments']);
        config()->set('queue.health.depth_threshold', 1000);

        $response = $this->getJson('/api/health');

        $queueCheck = $response->json('checks.queue');
        $this->assertIsArray($queueCheck, 'health response must include a queue check');
        $this->assertArrayHasKey('queues', $queueCheck, 'queue check must expose per-queue breakdown');
        $this->assertArrayHasKey('default', $queueCheck['queues']);
        $this->assertArrayHasKey('notifications', $queueCheck['queues']);
        $this->assertArrayHasKey('payments', $queueCheck['queues']);
        $this->assertArrayHasKey('threshold', $queueCheck);
    }
}
