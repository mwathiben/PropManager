<?php

namespace Tests\Unit\Services;

use App\Models\IdempotencyKey;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IdempotencyService;
    }

    public function test_acquire_returns_true_for_new_key(): void
    {
        $result = $this->service->acquire('test-key-123');

        $this->assertTrue($result['acquired']);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'test-key-123',
            'status' => 'processing',
        ]);
    }

    public function test_acquire_returns_false_for_existing_processing_key(): void
    {
        IdempotencyKey::create([
            'key' => 'existing-key',
            'status' => 'processing',
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->acquire('existing-key');

        $this->assertFalse($result['acquired']);
        $this->assertNull($result['response']);
        $this->assertEquals('processing', $result['status']);
    }

    public function test_acquire_returns_cached_response_for_completed_key(): void
    {
        $cachedResponse = ['status' => 'success', 'payment_id' => 123];

        IdempotencyKey::create([
            'key' => 'completed-key',
            'status' => 'completed',
            'response_data' => $cachedResponse,
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->acquire('completed-key');

        $this->assertFalse($result['acquired']);
        $this->assertEquals($cachedResponse, $result['response']);
    }

    public function test_acquire_deletes_expired_key_and_creates_new(): void
    {
        IdempotencyKey::create([
            'key' => 'expired-key',
            'status' => 'completed',
            'response_data' => ['old' => 'response'],
            'expires_at' => now()->subHours(1),
        ]);

        $result = $this->service->acquire('expired-key');

        $this->assertTrue($result['acquired']);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'expired-key',
            'status' => 'processing',
        ]);
    }

    public function test_acquire_stores_request_hash(): void
    {
        $requestHash = hash('sha256', json_encode(['amount' => 1000]));

        $result = $this->service->acquire('hash-key', $requestHash);

        $this->assertTrue($result['acquired']);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'hash-key',
            'request_hash' => $requestHash,
        ]);
    }

    public function test_release_stores_response_and_marks_completed(): void
    {
        IdempotencyKey::create([
            'key' => 'release-key',
            'status' => 'processing',
            'expires_at' => now()->addHours(24),
        ]);

        $response = ['status' => 'success', 'transaction_id' => 'ABC123'];
        $this->service->release('release-key', $response);

        $key = IdempotencyKey::where('key', 'release-key')->first();
        $this->assertEquals('completed', $key->status);
        $this->assertEquals($response, $key->response_data);
    }

    public function test_fail_marks_key_as_failed_with_reason(): void
    {
        IdempotencyKey::create([
            'key' => 'fail-key',
            'status' => 'processing',
            'expires_at' => now()->addHours(24),
        ]);

        $this->service->fail('fail-key', 'Insufficient funds');

        $key = IdempotencyKey::where('key', 'fail-key')->first();
        $this->assertEquals('failed', $key->status);
        $this->assertEquals(['error' => 'Insufficient funds'], $key->response_data);
    }

    public function test_fail_works_without_reason(): void
    {
        IdempotencyKey::create([
            'key' => 'fail-key-no-reason',
            'status' => 'processing',
            'expires_at' => now()->addHours(24),
        ]);

        $this->service->fail('fail-key-no-reason');

        $key = IdempotencyKey::where('key', 'fail-key-no-reason')->first();
        $this->assertEquals('failed', $key->status);
        $this->assertEquals(['error' => null], $key->response_data);
    }

    public function test_is_processing_returns_true_for_pending_key(): void
    {
        IdempotencyKey::create([
            'key' => 'pending-key',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertTrue($this->service->isProcessing('pending-key'));
    }

    public function test_is_processing_returns_true_for_processing_key(): void
    {
        IdempotencyKey::create([
            'key' => 'processing-key',
            'status' => 'processing',
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertTrue($this->service->isProcessing('processing-key'));
    }

    public function test_is_processing_returns_false_for_completed_key(): void
    {
        IdempotencyKey::create([
            'key' => 'completed-key',
            'status' => 'completed',
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertFalse($this->service->isProcessing('completed-key'));
    }

    public function test_is_processing_returns_false_for_expired_key(): void
    {
        IdempotencyKey::create([
            'key' => 'expired-processing-key',
            'status' => 'processing',
            'expires_at' => now()->subHours(1),
        ]);

        $this->assertFalse($this->service->isProcessing('expired-processing-key'));
    }

    public function test_is_processing_returns_false_for_nonexistent_key(): void
    {
        $this->assertFalse($this->service->isProcessing('nonexistent-key'));
    }

    public function test_cleanup_expired_removes_old_keys(): void
    {
        IdempotencyKey::create([
            'key' => 'old-key-1',
            'status' => 'completed',
            'expires_at' => now()->subHours(25),
        ]);

        IdempotencyKey::create([
            'key' => 'old-key-2',
            'status' => 'failed',
            'expires_at' => now()->subMinutes(5),
        ]);

        IdempotencyKey::create([
            'key' => 'active-key',
            'status' => 'processing',
            'expires_at' => now()->addHours(23),
        ]);

        $deleted = $this->service->cleanupExpired();

        $this->assertEquals(2, $deleted);
        $this->assertDatabaseMissing('idempotency_keys', ['key' => 'old-key-1']);
        $this->assertDatabaseMissing('idempotency_keys', ['key' => 'old-key-2']);
        $this->assertDatabaseHas('idempotency_keys', ['key' => 'active-key']);
    }

    public function test_cleanup_expired_returns_zero_when_no_expired_keys(): void
    {
        IdempotencyKey::create([
            'key' => 'active-key',
            'status' => 'processing',
            'expires_at' => now()->addHours(24),
        ]);

        $deleted = $this->service->cleanupExpired();

        $this->assertEquals(0, $deleted);
    }

    public function test_acquire_sets_expires_at_to_configured_ttl(): void
    {
        $this->freezeTime();

        $ttlHours = (int) config('services.idempotency.ttl_hours', 2160);
        $result = $this->service->acquire('ttl-key');

        $key = IdempotencyKey::where('key', 'ttl-key')->first();
        $this->assertTrue($result['acquired']);
        $this->assertEquals(now()->addHours($ttlHours)->timestamp, $key->expires_at->timestamp);
    }
}
