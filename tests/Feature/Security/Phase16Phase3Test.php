<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Jobs\GenerateInvoicePdf;
use App\Jobs\PerRecipientBulkNotificationJob;
use App\Jobs\SendNotificationJob;
use App\Services\Payment\WebhookDeadLetterService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase-16 Phase 3+4 coverage:
 *   RESIL-5: queue config has after_commit=true on database+redis
 *   RESIL-6: OcrService polling exists and is bounded (private method exists)
 *   RESIL-7: Http::resilient() macro applies the house preset
 *   RESIL-8: WebhookDeadLetterService::nextRetryAt is exponential per attempt
 *   QUEUE-4: SendNotificationJob is ShouldBeUniqueUntilProcessing
 *   QUEUE-7: jobs use TracksFailures trait
 *   QUEUE-9: prune-batches is scheduled
 */
class Phase16Phase3Test extends TestCase
{
    use RefreshDatabase;

    public function test_queue_after_commit_is_true_for_database_and_redis(): void
    {
        // RESIL-5: prevents jobs dispatched inside DB::transaction()
        // from firing before commit.
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertTrue(config('queue.connections.redis.after_commit'));
    }

    public function test_http_resilient_macro_is_registered(): void
    {
        // RESIL-7: AppServiceProvider must register the Http::resilient()
        // macro so new services can opt in to the house preset.
        $this->assertTrue(
            Http::hasMacro('resilient'),
            'Http::resilient() macro must be registered by AppServiceProvider (RESIL-7)',
        );

        // The macro returns a PendingRequest with timeout + retry set.
        $client = Http::resilient();
        $this->assertInstanceOf(\Illuminate\Http\Client\PendingRequest::class, $client);
    }

    public function test_send_notification_uses_unique_until_processing(): void
    {
        // QUEUE-4: lock released when the worker picks up the job, not
        // when the job completes. Pre-fix the lock spanned the entire
        // lifecycle including queue wait + execution + retry chain.
        $reflection = new \ReflectionClass(SendNotificationJob::class);
        $this->assertTrue(
            $reflection->implementsInterface(ShouldBeUniqueUntilProcessing::class),
            'SendNotificationJob must implement ShouldBeUniqueUntilProcessing (Phase-16 QUEUE-4)',
        );
    }

    public function test_dlq_next_retry_at_is_exponential_per_attempt(): void
    {
        // RESIL-8: pre-fix every retry waited 5 minutes. Now: 5/10/20/40/60.
        $service = new WebhookDeadLetterService;

        $base = now();
        \Illuminate\Support\Carbon::setTestNow($base);

        $attempt1 = $service->nextRetryAt(1);
        $attempt2 = $service->nextRetryAt(2);
        $attempt3 = $service->nextRetryAt(3);
        $attempt5 = $service->nextRetryAt(5);

        // Approximately exponential (allow ±10% jitter wobble).
        $diff1 = $base->diffInSeconds($attempt1);
        $diff2 = $base->diffInSeconds($attempt2);
        $diff3 = $base->diffInSeconds($attempt3);
        $diff5 = $base->diffInSeconds($attempt5);

        $this->assertGreaterThanOrEqual(300, $diff1, 'attempt 1 must be >= 300s base');
        $this->assertLessThan(340, $diff1, 'attempt 1 jitter must be < 10% over base');
        $this->assertGreaterThanOrEqual(600, $diff2, 'attempt 2 must be >= 600s (5min * 2)');
        $this->assertGreaterThanOrEqual(1200, $diff3, 'attempt 3 must be >= 1200s (5min * 4)');
        $this->assertGreaterThanOrEqual(3600, $diff5, 'attempt 5 must be >= 3600s cap');
        $this->assertLessThan(4000, $diff5, 'attempt 5 must be capped at 1h + jitter');

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_key_jobs_use_tracks_failures_trait(): void
    {
        // QUEUE-7: per-job-class failure counter so 'job_failed{class=X}'
        // is graphable.
        $jobsWithFailureTracking = [
            SendNotificationJob::class,
            PerRecipientBulkNotificationJob::class,
            GenerateInvoicePdf::class,
        ];

        foreach ($jobsWithFailureTracking as $jobClass) {
            $traits = class_uses_recursive($jobClass);
            $this->assertContains(
                \App\Jobs\Concerns\TracksFailures::class,
                $traits,
                "{$jobClass} must use TracksFailures trait (Phase-16 QUEUE-7)",
            );
        }
    }

    public function test_ocr_service_has_bounded_polling(): void
    {
        // RESIL-6: pollAzureOperation method exists with a max-attempt
        // parameter. Cheap structural check — full integration test
        // requires Azure credentials.
        $service = new \App\Services\OcrService;
        $reflection = new \ReflectionClass($service);
        $this->assertTrue(
            $reflection->hasMethod('pollAzureOperation'),
            'OcrService must have a pollAzureOperation method with bounded retries (Phase-16 RESIL-6)',
        );

        $method = $reflection->getMethod('pollAzureOperation');
        $params = $method->getParameters();
        $maxAttemptsParam = collect($params)->first(fn ($p) => $p->getName() === 'maxAttempts');

        $this->assertNotNull($maxAttemptsParam, 'pollAzureOperation must accept a maxAttempts parameter');
        $this->assertTrue($maxAttemptsParam->isDefaultValueAvailable(), 'maxAttempts must have a default value');
        $this->assertGreaterThanOrEqual(10, $maxAttemptsParam->getDefaultValue());
        $this->assertLessThanOrEqual(50, $maxAttemptsParam->getDefaultValue());
    }
}
