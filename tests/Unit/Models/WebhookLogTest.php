<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookLogTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    public function test_can_create_webhook_log_with_factory(): void
    {
        $log = WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
        ]);

        $this->assertDatabaseHas('webhook_logs', [
            'id' => $log->id,
            'landlord_id' => $this->landlord->id,
            'provider' => WebhookLog::PROVIDER_MPESA,
        ]);
    }

    public function test_provider_constants_match_webhook_dead_letter(): void
    {
        $this->assertEquals('mpesa', WebhookLog::PROVIDER_MPESA);
        $this->assertEquals('intasend', WebhookLog::PROVIDER_INTASEND);
        $this->assertEquals('paystack', WebhookLog::PROVIDER_PAYSTACK);
        $this->assertEquals('bank', WebhookLog::PROVIDER_BANK);
    }

    public function test_status_constants_defined(): void
    {
        $this->assertEquals('pending', WebhookLog::STATUS_PENDING);
        $this->assertEquals('processed', WebhookLog::STATUS_PROCESSED);
        $this->assertEquals('failed', WebhookLog::STATUS_FAILED);
    }

    public function test_retry_count_cast_to_integer(): void
    {
        $log = WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
            'retry_count' => 3,
        ]);

        $log->refresh();

        $this->assertIsInt($log->retry_count);
        $this->assertEquals(3, $log->retry_count);
    }

    public function test_processing_time_ms_cast_to_integer(): void
    {
        $log = WebhookLog::factory()->processed()->create([
            'landlord_id' => $this->landlord->id,
            'processing_time_ms' => 250,
        ]);

        $log->refresh();

        $this->assertIsInt($log->processing_time_ms);
        $this->assertEquals(250, $log->processing_time_ms);
    }

    public function test_timestamps_cast_to_datetime(): void
    {
        $log = WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
        ]);

        $log->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->first_received_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->last_received_at);
    }

    public function test_landlord_relationship(): void
    {
        $log = WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
        ]);

        $this->assertInstanceOf(User::class, $log->landlord);
        $this->assertEquals($this->landlord->id, $log->landlord->id);
    }

    public function test_landlord_id_nullable(): void
    {
        $log = WebhookLog::factory()->create([
            'landlord_id' => null,
        ]);

        $this->assertDatabaseHas('webhook_logs', [
            'id' => $log->id,
            'landlord_id' => null,
        ]);
        $this->assertNull($log->landlord);
    }

    public function test_by_provider_scope_filters_correctly(): void
    {
        WebhookLog::factory()->mpesa()->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->intasend()->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->paystack()->create(['landlord_id' => $this->landlord->id]);

        $this->actingAs($this->landlord);

        $mpesaOnly = WebhookLog::byProvider(WebhookLog::PROVIDER_MPESA)->get();

        $this->assertCount(1, $mpesaOnly);
        $this->assertEquals(WebhookLog::PROVIDER_MPESA, $mpesaOnly->first()->provider);
    }

    public function test_high_retry_scope_returns_above_threshold(): void
    {
        $this->actingAs($this->landlord);

        WebhookLog::factory()->withRetries(1)->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->withRetries(2)->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->withRetries(3)->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->withRetries(5)->create(['landlord_id' => $this->landlord->id]);

        $highRetry = WebhookLog::highRetry(3)->get();

        $this->assertCount(2, $highRetry);
        $this->assertTrue($highRetry->every(fn ($log) => $log->retry_count >= 3));
    }

    public function test_recent_scope_returns_within_hours(): void
    {
        $this->actingAs($this->landlord);

        WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
            'last_received_at' => now()->subHours(2),
        ]);
        WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
            'last_received_at' => now()->subHours(25),
        ]);

        $recent = WebhookLog::recent(24)->get();

        $this->assertCount(1, $recent);
    }

    public function test_with_status_scope(): void
    {
        $this->actingAs($this->landlord);

        WebhookLog::factory()->processed()->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->failed()->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->create(['landlord_id' => $this->landlord->id]);

        $processed = WebhookLog::withStatus(WebhookLog::STATUS_PROCESSED)->get();

        $this->assertCount(1, $processed);
    }

    public function test_failed_scope(): void
    {
        $this->actingAs($this->landlord);

        WebhookLog::factory()->processed()->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->failed()->create(['landlord_id' => $this->landlord->id]);
        WebhookLog::factory()->failed()->create(['landlord_id' => $this->landlord->id]);

        $failed = WebhookLog::failed()->get();

        $this->assertCount(2, $failed);
    }

    public function test_mark_processed_updates_status_and_time(): void
    {
        $log = WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
        ]);

        $log->markProcessed(150);
        $log->refresh();

        $this->assertEquals(WebhookLog::STATUS_PROCESSED, $log->status);
        $this->assertEquals(150, $log->processing_time_ms);
    }

    public function test_mark_failed_updates_status_and_time(): void
    {
        $log = WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
        ]);

        $log->markFailed(500);
        $log->refresh();

        $this->assertEquals(WebhookLog::STATUS_FAILED, $log->status);
        $this->assertEquals(500, $log->processing_time_ms);
    }

    public function test_is_retry_returns_true_when_count_above_one(): void
    {
        $firstHit = WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
            'retry_count' => 1,
        ]);

        $retry = WebhookLog::factory()->create([
            'landlord_id' => $this->landlord->id,
            'retry_count' => 2,
        ]);

        $this->assertFalse($firstHit->isRetry());
        $this->assertTrue($retry->isRetry());
    }

    public function test_tenant_scope_isolates_by_landlord(): void
    {
        $landlord2 = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($this->landlord);

        WebhookLog::clearBootedModels();

        WebhookLog::withoutGlobalScope('landlord')->insert(
            collect(range(1, 3))->map(fn ($i) => [
                'landlord_id' => $this->landlord->id,
                'provider' => WebhookLog::PROVIDER_MPESA,
                'event_id' => 'EVT-'.$i,
                'event_type' => 'stk_callback',
                'payload_hash' => hash('sha256', 'test'.$i),
                'retry_count' => 1,
                'first_received_at' => now(),
                'last_received_at' => now(),
                'status' => WebhookLog::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray()
        );

        WebhookLog::withoutGlobalScope('landlord')->insert(
            collect(range(1, 2))->map(fn ($i) => [
                'landlord_id' => $landlord2->id,
                'provider' => WebhookLog::PROVIDER_INTASEND,
                'event_id' => 'INT-'.$i,
                'event_type' => 'payment.complete',
                'payload_hash' => hash('sha256', 'intasend'.$i),
                'retry_count' => 1,
                'first_received_at' => now(),
                'last_received_at' => now(),
                'status' => WebhookLog::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray()
        );

        $landlord1Results = WebhookLog::all();
        $this->assertCount(3, $landlord1Results);

        $this->actingAs($landlord2);
        $landlord2Results = WebhookLog::all();
        $this->assertCount(2, $landlord2Results);
    }
}
