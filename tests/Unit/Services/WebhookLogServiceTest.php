<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\WebhookLog;
use App\Services\Payment\WebhookLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WebhookLogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WebhookLogService $service;

    protected User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WebhookLogService::class);
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    public function test_record_hit_creates_new_log_entry(): void
    {
        $log = $this->service->recordHit(
            WebhookLog::PROVIDER_MPESA,
            'QKL123456789',
            'stk_callback',
            '{"amount":5000,"receipt":"QKL123456789"}',
            $this->landlord->id,
            '196.201.214.200'
        );

        $this->assertInstanceOf(WebhookLog::class, $log);
        $this->assertEquals(1, $log->retry_count);
        $this->assertEquals(WebhookLog::PROVIDER_MPESA, $log->provider);
        $this->assertEquals('QKL123456789', $log->event_id);
        $this->assertEquals('stk_callback', $log->event_type);
        $this->assertEquals(WebhookLog::STATUS_PENDING, $log->status);
        $this->assertEquals($this->landlord->id, $log->landlord_id);
        $this->assertEquals('196.201.214.200', $log->ip_address);
        $this->assertNotNull($log->payload_hash);
        $this->assertEquals(64, strlen($log->payload_hash));

        $this->assertDatabaseHas('webhook_logs', [
            'provider' => 'mpesa',
            'event_id' => 'QKL123456789',
            'retry_count' => 1,
        ]);
    }

    public function test_record_hit_increments_retry_count_on_duplicate(): void
    {
        $first = $this->service->recordHit(
            WebhookLog::PROVIDER_MPESA,
            'QKL999888777',
            'stk_callback',
            '{"amount":5000}',
            $this->landlord->id
        );

        $this->assertEquals(1, $first->retry_count);

        $second = $this->service->recordHit(
            WebhookLog::PROVIDER_MPESA,
            'QKL999888777',
            'stk_callback',
            '{"amount":5000,"retry":true}',
            $this->landlord->id
        );

        $this->assertEquals(2, $second->retry_count);

        $this->assertDatabaseCount('webhook_logs', 1);
    }

    public function test_record_hit_updates_last_received_at_on_retry(): void
    {
        $first = $this->service->recordHit(
            WebhookLog::PROVIDER_INTASEND,
            'ISR-ABC-123',
            'payment.complete',
            '{"api_ref":"ISR-ABC-123"}',
            $this->landlord->id
        );

        $firstReceivedAt = $first->first_received_at->copy();
        $firstLastReceivedAt = $first->last_received_at->copy();

        $this->travel(5)->minutes();

        $second = $this->service->recordHit(
            WebhookLog::PROVIDER_INTASEND,
            'ISR-ABC-123',
            'payment.complete',
            '{"api_ref":"ISR-ABC-123","retry":true}',
            $this->landlord->id
        );

        $this->assertEquals($firstReceivedAt->toDateTimeString(), $second->first_received_at->toDateTimeString());
        $this->assertTrue($second->last_received_at->greaterThan($firstLastReceivedAt));
    }

    public function test_record_hit_updates_payload_hash_on_retry(): void
    {
        $payload1 = '{"amount":5000,"attempt":1}';
        $payload2 = '{"amount":5000,"attempt":2}';

        $first = $this->service->recordHit(
            WebhookLog::PROVIDER_MPESA,
            'QKL-HASH-TEST',
            'stk_callback',
            $payload1,
            $this->landlord->id
        );

        $firstHash = $first->payload_hash;

        $second = $this->service->recordHit(
            WebhookLog::PROVIDER_MPESA,
            'QKL-HASH-TEST',
            'stk_callback',
            $payload2,
            $this->landlord->id
        );

        $this->assertNotEquals($firstHash, $second->payload_hash);
        $this->assertEquals(hash('sha256', $payload2), $second->payload_hash);
    }

    public function test_record_hit_logs_warning_at_high_retry_threshold(): void
    {
        Log::spy();

        WebhookLog::withoutGlobalScope('landlord')->create([
            'landlord_id' => $this->landlord->id,
            'provider' => WebhookLog::PROVIDER_MPESA,
            'event_id' => 'QKL-HIGH-RETRY',
            'event_type' => 'stk_callback',
            'payload_hash' => hash('sha256', 'test'),
            'retry_count' => 2,
            'first_received_at' => now()->subMinutes(10),
            'last_received_at' => now()->subMinutes(5),
            'status' => WebhookLog::STATUS_PENDING,
        ]);

        $this->service->recordHit(
            WebhookLog::PROVIDER_MPESA,
            'QKL-HIGH-RETRY',
            'stk_callback',
            '{"retry":3}',
            $this->landlord->id
        );

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'high retry count'))
            ->once();
    }

    public function test_timing_records_processing_time_ms(): void
    {
        $log = WebhookLog::factory()->create(['landlord_id' => $this->landlord->id]);

        $this->service->startTiming('test-timer');
        usleep(50000); // 50ms
        $this->service->finishTiming($log, 'test-timer', WebhookLog::STATUS_PROCESSED);

        $log->refresh();

        $this->assertEquals(WebhookLog::STATUS_PROCESSED, $log->status);
        $this->assertNotNull($log->processing_time_ms);
        $this->assertGreaterThanOrEqual(40, $log->processing_time_ms);
    }

    public function test_finish_timing_marks_processed(): void
    {
        $log = WebhookLog::factory()->create(['landlord_id' => $this->landlord->id]);

        $this->service->startTiming('proc-test');
        $this->service->finishTiming($log, 'proc-test', WebhookLog::STATUS_PROCESSED);

        $log->refresh();

        $this->assertEquals(WebhookLog::STATUS_PROCESSED, $log->status);
        $this->assertNotNull($log->processing_time_ms);
    }

    public function test_finish_timing_marks_failed(): void
    {
        $log = WebhookLog::factory()->create(['landlord_id' => $this->landlord->id]);

        $this->service->startTiming('fail-test');
        $this->service->finishTiming($log, 'fail-test', WebhookLog::STATUS_FAILED);

        $log->refresh();

        $this->assertEquals(WebhookLog::STATUS_FAILED, $log->status);
        $this->assertNotNull($log->processing_time_ms);
    }

    public function test_finish_timing_without_start_uses_zero(): void
    {
        $log = WebhookLog::factory()->create(['landlord_id' => $this->landlord->id]);

        $this->service->finishTiming($log, 'nonexistent-timer', WebhookLog::STATUS_PROCESSED);

        $log->refresh();

        $this->assertEquals(WebhookLog::STATUS_PROCESSED, $log->status);
        $this->assertEquals(0, $log->processing_time_ms);
    }

    public function test_record_hit_handles_null_landlord_id(): void
    {
        $log = $this->service->recordHit(
            WebhookLog::PROVIDER_MPESA,
            'QKL-NULL-LANDLORD',
            'stk_callback',
            '{"amount":5000}',
            null,
            '196.201.214.200'
        );

        $this->assertInstanceOf(WebhookLog::class, $log);
        $this->assertNull($log->landlord_id);
        $this->assertDatabaseHas('webhook_logs', [
            'event_id' => 'QKL-NULL-LANDLORD',
            'landlord_id' => null,
        ]);
    }

    public function test_record_hit_resets_status_to_pending_on_retry(): void
    {
        $log = WebhookLog::withoutGlobalScope('landlord')->create([
            'landlord_id' => $this->landlord->id,
            'provider' => WebhookLog::PROVIDER_MPESA,
            'event_id' => 'QKL-RESET-STATUS',
            'event_type' => 'stk_callback',
            'payload_hash' => hash('sha256', 'original'),
            'retry_count' => 1,
            'first_received_at' => now()->subMinutes(5),
            'last_received_at' => now()->subMinutes(5),
            'status' => WebhookLog::STATUS_FAILED,
        ]);

        $retried = $this->service->recordHit(
            WebhookLog::PROVIDER_MPESA,
            'QKL-RESET-STATUS',
            'stk_callback',
            '{"retry":true}',
            $this->landlord->id
        );

        $this->assertEquals(WebhookLog::STATUS_PENDING, $retried->status);
        $this->assertEquals(2, $retried->retry_count);
    }
}
