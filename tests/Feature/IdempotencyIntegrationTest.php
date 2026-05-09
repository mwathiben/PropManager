<?php

namespace Tests\Feature;

use App\Models\IdempotencyKey;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class IdempotencyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private IdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IdempotencyService;
    }

    public function test_concurrent_requests_only_one_acquires(): void
    {
        $key = 'concurrent-test-key-'.uniqid();
        $acquiredCount = 0;
        $notAcquiredCount = 0;

        $threads = 10;
        $promises = [];

        for ($i = 0; $i < $threads; $i++) {
            $result = $this->service->acquire($key);
            if ($result['acquired']) {
                $acquiredCount++;
            } else {
                $notAcquiredCount++;
            }
        }

        $this->assertEquals(1, $acquiredCount, 'Exactly one request should acquire the key');
        $this->assertEquals($threads - 1, $notAcquiredCount, 'All other requests should not acquire');
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_duplicate_request_gets_cached_response(): void
    {
        $key = 'cache-test-key';
        $expectedResponse = ['payment_id' => 999, 'status' => 'success'];

        $firstResult = $this->service->acquire($key);
        $this->assertTrue($firstResult['acquired']);

        $this->service->release($key, $expectedResponse);

        $secondResult = $this->service->acquire($key);
        $this->assertFalse($secondResult['acquired']);
        $this->assertEquals($expectedResponse, $secondResult['response']);

        $thirdResult = $this->service->acquire($key);
        $this->assertFalse($thirdResult['acquired']);
        $this->assertEquals($expectedResponse, $thirdResult['response']);
    }

    public function test_cleanup_command_removes_expired_keys(): void
    {
        IdempotencyKey::create([
            'key' => 'expired-for-cleanup',
            'status' => 'completed',
            'expires_at' => now()->subHours(25),
        ]);

        IdempotencyKey::create([
            'key' => 'active-for-cleanup',
            'status' => 'processing',
            'expires_at' => now()->addHours(23),
        ]);

        Artisan::call('idempotency:cleanup');

        $this->assertDatabaseMissing('idempotency_keys', ['key' => 'expired-for-cleanup']);
        $this->assertDatabaseHas('idempotency_keys', ['key' => 'active-for-cleanup']);
    }

    public function test_cleanup_command_outputs_count(): void
    {
        IdempotencyKey::create([
            'key' => 'expired-1',
            'status' => 'completed',
            'expires_at' => now()->subHours(25),
        ]);

        IdempotencyKey::create([
            'key' => 'expired-2',
            'status' => 'failed',
            'expires_at' => now()->subMinutes(10),
        ]);

        $exitCode = Artisan::call('idempotency:cleanup');
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Deleted 2 expired key(s)', $output);
    }

    public function test_workflow_acquire_process_release(): void
    {
        $key = 'workflow-key';
        $requestHash = hash('sha256', json_encode(['invoice_id' => 100]));

        $acquire = $this->service->acquire($key, $requestHash);
        $this->assertTrue($acquire['acquired']);

        $this->assertTrue($this->service->isProcessing($key));

        $response = ['payment_id' => 123, 'status' => 'paid'];
        $this->service->release($key, $response);

        $this->assertFalse($this->service->isProcessing($key));

        $retry = $this->service->acquire($key);
        $this->assertFalse($retry['acquired']);
        $this->assertEquals($response, $retry['response']);
    }

    public function test_workflow_acquire_process_fail(): void
    {
        $key = 'fail-workflow-key';

        $acquire = $this->service->acquire($key);
        $this->assertTrue($acquire['acquired']);

        $this->service->fail($key, 'Payment declined');

        $dbKey = IdempotencyKey::where('key', $key)->first();
        $this->assertEquals('failed', $dbKey->status);
        $this->assertEquals(['error' => 'Payment declined'], $dbKey->response_data);
    }

    public function test_different_keys_can_be_acquired_simultaneously(): void
    {
        $result1 = $this->service->acquire('key-a');
        $result2 = $this->service->acquire('key-b');
        $result3 = $this->service->acquire('key-c');

        $this->assertTrue($result1['acquired']);
        $this->assertTrue($result2['acquired']);
        $this->assertTrue($result3['acquired']);
        $this->assertDatabaseCount('idempotency_keys', 3);
    }

    public function test_key_with_provider_prefix_pattern(): void
    {
        $mpesaKey = 'mpesa:QKL123456789';
        $intasendKey = 'intasend:ABC-1234567';
        $paystackKey = 'paystack:REF_xyz123';

        $this->assertTrue($this->service->acquire($mpesaKey)['acquired']);
        $this->assertTrue($this->service->acquire($intasendKey)['acquired']);
        $this->assertTrue($this->service->acquire($paystackKey)['acquired']);

        $this->assertFalse($this->service->acquire($mpesaKey)['acquired']);
        $this->assertFalse($this->service->acquire($intasendKey)['acquired']);
        $this->assertFalse($this->service->acquire($paystackKey)['acquired']);
    }

    public function test_expired_completed_key_remains_idempotent(): void
    {
        // CONC-12: a completed idempotency key whose TTL has technically
        // expired must NOT be replaced — replaying a finalized webhook
        // re-enters processing and (without the wallet_transactions
        // unique index) double-credits the wallet.
        $key = 'reusable-key';
        $oldResponse = ['old' => 'data'];

        IdempotencyKey::create([
            'key' => $key,
            'status' => 'completed',
            'response_data' => $oldResponse,
            'expires_at' => now()->subHours(1),
        ]);

        $acquire = $this->service->acquire($key);
        $this->assertFalse($acquire['acquired'], 'Completed key past TTL must stay idempotent.');
        $this->assertSame($oldResponse, $acquire['response']);
    }

    public function test_expired_pending_key_allows_new_acquisition(): void
    {
        // Pending/processing keys (request started, never finished) ARE
        // safe to replace on TTL expiry — they represent a stalled or
        // crashed handler, not a finalized response.
        $key = 'stalled-key';

        IdempotencyKey::create([
            'key' => $key,
            'status' => 'processing',
            'expires_at' => now()->subHours(1),
        ]);

        $acquire = $this->service->acquire($key);
        $this->assertTrue($acquire['acquired']);
    }
}
