<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Enums\Currency;
use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRYPTO-11: bank webhook signature validation must prefer the
 * destination landlord's per-landlord secret over the env-wide one.
 *
 * Two guarantees are locked in here:
 *   1. A signature crafted with the per-landlord secret is accepted
 *      even when the env secret differs (per-landlord OVERRIDES env).
 *   2. When the landlord has not configured a per-landlord secret,
 *      the env secret is still honoured (cutover-safe fallback).
 *   3. A signature crafted with the env secret while the landlord has
 *      a per-landlord secret is REJECTED — that's the leak isolation
 *      property the finding is about.
 */
class BankWebhookPerLandlordSecretTest extends TestCase
{
    use RefreshDatabase;

    private function makeLandlordWithConfig(?string $coopSecret, string $accountNumber): User
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        PaymentConfiguration::create([
            'landlord_id' => $landlord->id,
            'bank_name' => 'coop',
            'bank_account_number' => $accountNumber,
            'coop_webhook_secret' => $coopSecret,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
            'accepted_payment_methods' => ['bank_transfer'],
            'default_currency' => Currency::KES,
            'paystack_enabled' => false,
            'intasend_enabled' => false,
        ]);

        return $landlord;
    }

    private function coopPayload(string $accountNumber): array
    {
        return [
            'TransactionID' => 'TX'.uniqid(),
            'Amount' => 1000,
            'AccountNumber' => $accountNumber,
            'TransactionDate' => now()->toDateTimeString(),
            'Narration' => 'Test reference',
        ];
    }

    private function postWebhook(string $rawBody, string $signature)
    {
        return $this->call(
            'POST',
            '/api/webhooks/bank/coop',
            [],
            [],
            [],
            [
                'HTTP_X-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawBody
        );
    }

    public function test_accepts_signature_built_with_per_landlord_secret(): void
    {
        $accountNumber = '10000001';
        $this->makeLandlordWithConfig('per-landlord-secret', $accountNumber);
        config(['services.coop.webhook_secret' => 'env-secret']);

        $body = json_encode($this->coopPayload($accountNumber));
        $sig = hash_hmac('sha256', $body, 'per-landlord-secret');

        $response = $this->postWebhook($body, $sig);

        $response->assertStatus(200);
    }

    public function test_rejects_signature_built_with_env_secret_when_per_landlord_secret_exists(): void
    {
        $accountNumber = '10000002';
        $this->makeLandlordWithConfig('per-landlord-secret', $accountNumber);
        config(['services.coop.webhook_secret' => 'env-secret']);

        $body = json_encode($this->coopPayload($accountNumber));
        $sig = hash_hmac('sha256', $body, 'env-secret');

        $response = $this->postWebhook($body, $sig);

        $response->assertStatus(401);
    }

    public function test_falls_back_to_env_secret_when_landlord_has_no_per_landlord_secret(): void
    {
        $accountNumber = '10000003';
        $this->makeLandlordWithConfig(null, $accountNumber);
        config(['services.coop.webhook_secret' => 'env-secret']);

        $body = json_encode($this->coopPayload($accountNumber));
        $sig = hash_hmac('sha256', $body, 'env-secret');

        $response = $this->postWebhook($body, $sig);

        $response->assertStatus(200);
    }

    public function test_rejects_signature_when_no_secret_is_configured_anywhere(): void
    {
        $accountNumber = '10000004';
        $this->makeLandlordWithConfig(null, $accountNumber);
        config(['services.coop.webhook_secret' => null]);

        $body = json_encode($this->coopPayload($accountNumber));
        $sig = hash_hmac('sha256', $body, 'anything');

        $response = $this->postWebhook($body, $sig);

        $response->assertStatus(401);
    }
}
