<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Phase30PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_phase30_dummy_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => self::SECRET]);
    }

    public function test_valid_hmac_sha512_signature_is_accepted(): void
    {
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'rcpt_abc_1']];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha512', $body, self::SECRET);

        $this->call(
            method: 'POST',
            uri: route('webhooks.v2.paystack'),
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $sig,
            ],
        )->assertOk()->assertJson(['status' => 'accepted']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'rcpt_abc_2']]);

        $this->call(
            method: 'POST',
            uri: route('webhooks.v2.paystack'),
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => 'deadbeef',
            ],
        )->assertStatus(401);
    }

    public function test_missing_secret_returns_503(): void
    {
        config(['services.paystack.secret_key' => '']);
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'rcpt_abc_3']]);

        $this->call(
            method: 'POST',
            uri: route('webhooks.v2.paystack'),
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => 'whatever',
            ],
        )->assertStatus(503);
    }

    public function test_duplicate_event_reference_returns_duplicate(): void
    {
        Cache::flush();
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'rcpt_dup_1']];
        $body = json_encode($payload);
        $sig = hash_hmac('sha512', $body, self::SECRET);

        $this->call('POST', route('webhooks.v2.paystack'), content: $body, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $sig,
        ])->assertOk();

        $this->call('POST', route('webhooks.v2.paystack'), content: $body, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $sig,
        ])->assertOk()->assertJson(['status' => 'duplicate']);
    }

    public function test_invalid_payload_shape_returns_422(): void
    {
        $body = json_encode(['event' => 'charge.success']);
        $sig = hash_hmac('sha512', $body, self::SECRET);

        $this->call('POST', route('webhooks.v2.paystack'), content: $body, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $sig,
        ])->assertStatus(422);
    }
}
