<?php

namespace Tests\Unit\Services;

use App\Models\PaymentConfiguration;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaystackServiceCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected PaystackService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $config = PaymentConfiguration::factory()->create([
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_currency',
            'paystack_secret_key' => 'sk_test_currency',
        ]);

        $this->service = new PaystackService($config);
    }

    #[Test]
    public function initialize_sends_currency_to_paystack_api(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test_access',
                    'reference' => 'REF-123',
                ],
            ]),
        ]);

        $this->service->initializeTransaction([
            'email' => 'tenant@test.com',
            'amount' => 100.00,
            'reference' => 'REF-123',
            'currency' => 'USD',
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['currency'] === 'USD'
                && $body['amount'] === 10000;
        });
    }

    #[Test]
    public function initialize_converts_amount_using_currency_enum(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test_access',
                    'reference' => 'REF-456',
                ],
            ]),
        ]);

        $this->service->initializeTransaction([
            'email' => 'tenant@test.com',
            'amount' => 50.75,
            'reference' => 'REF-456',
            'currency' => 'GBP',
        ]);

        Http::assertSent(function ($request) {
            return $request->data()['amount'] === 5075
                && $request->data()['currency'] === 'GBP';
        });
    }

    #[Test]
    public function initialize_defaults_to_kes_without_currency(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test_access',
                    'reference' => 'REF-789',
                ],
            ]),
        ]);

        $this->service->initializeTransaction([
            'email' => 'tenant@test.com',
            'amount' => 5000,
            'reference' => 'REF-789',
        ]);

        Http::assertSent(function ($request) {
            return $request->data()['currency'] === 'KES'
                && $request->data()['amount'] === 500000;
        });
    }

    #[Test]
    public function split_transaction_sends_currency(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test_access',
                    'reference' => 'SPLIT-123',
                ],
            ]),
        ]);

        $this->service->initializeSplitTransaction([
            'email' => 'tenant@test.com',
            'amount' => 200.00,
            'reference' => 'SPLIT-123',
            'currency' => 'USD',
            'subaccount_code' => 'ACCT_test123',
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['currency'] === 'USD'
                && $body['amount'] === 20000;
        });
    }

    #[Test]
    public function refund_converts_amount_with_currency(): void
    {
        Http::fake([
            'api.paystack.co/refund' => Http::response([
                'status' => true,
                'data' => [
                    'amount' => 5000,
                    'currency' => 'USD',
                ],
            ]),
        ]);

        $this->service->refundTransaction('REF-REFUND', 50.00, 'USD');

        Http::assertSent(function ($request) {
            return $request->data()['amount'] === 5000;
        });
    }
}
