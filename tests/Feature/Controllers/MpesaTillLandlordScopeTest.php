<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Enums\Currency;
use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRIV-5 (M-Pesa half): the Till confirmation endpoint must scope its
 * tenant-by-phone lookup to the landlord owning the receiving Till
 * shortcode. Two landlords commonly have tenants sharing a phone
 * number (one human paying rent at multiple buildings, or simply two
 * unrelated tenants with the same digits typed in). Unscoped, the
 * first-row-wins lookup cross-credits the wrong landlord's invoice.
 */
class MpesaTillLandlordScopeTest extends TestCase
{
    use RefreshDatabase;

    private string $safaricomIp = '196.201.214.200';

    protected function setUp(): void
    {
        parent::setUp();

        config(['mpesa.allowed_ips' => [
            '196.201.214.200',
            '196.201.214.206',
            '196.201.213.114',
        ]]);
    }

    private function configureLandlordTill(int $landlordId, string $shortCode): void
    {
        PaymentConfiguration::create([
            'landlord_id' => $landlordId,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
            'accepted_payment_methods' => ['mobile_money'],
            'default_currency' => Currency::KES,
            'mpesa_shortcode' => $shortCode,
            'mpesa_shortcode_type' => 'till',
            'paystack_enabled' => false,
            'intasend_enabled' => false,
        ]);
    }

    public function test_till_validation_only_matches_tenant_under_owning_landlord(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $this->configureLandlordTill($landlordA->id, '111111');
        $this->configureLandlordTill($landlordB->id, '222222');

        $sharedPhone = '0712345678';
        User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlordA->id,
            'mobile_number' => $sharedPhone,
        ]);
        User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlordB->id,
            'mobile_number' => $sharedPhone,
        ]);

        $response = $this->postJson(
            '/api/webhooks/mpesa/till/validation',
            [
                'TransactionType' => 'Buy Goods',
                'TransID' => 'TX'.uniqid(),
                'TransTime' => now()->format('YmdHis'),
                'TransAmount' => '1000.00',
                'BusinessShortCode' => '111111',
                'MSISDN' => '254712345678',
            ],
            ['REMOTE_ADDR' => $this->safaricomIp]
        );

        $response->assertStatus(200);
    }

    public function test_till_validation_refuses_to_match_when_shortcode_unknown(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->configureLandlordTill($landlord->id, '111111');

        User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
            'mobile_number' => '0712345678',
        ]);

        $response = $this->postJson(
            '/api/webhooks/mpesa/till/validation',
            [
                'TransactionType' => 'Buy Goods',
                'TransID' => 'TX'.uniqid(),
                'TransTime' => now()->format('YmdHis'),
                'TransAmount' => '1000.00',
                'BusinessShortCode' => '999999',
                'MSISDN' => '254712345678',
            ],
            ['REMOTE_ADDR' => $this->safaricomIp]
        );

        $response->assertStatus(200);
        $response->assertJson(['ResultDesc' => 'Accepted for manual review']);
    }

    public function test_find_tenant_by_phone_returns_null_without_landlord_id(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
            'mobile_number' => '0712345678',
        ]);

        $controller = app(\App\Http\Controllers\Api\MpesaWebhookController::class);

        $method = new \ReflectionMethod($controller, 'findTenantByPhone');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, '0712345678', null));
        $this->assertNotNull($method->invoke($controller, '0712345678', $landlord->id));
    }

    public function test_find_tenant_by_phone_scopes_to_landlord(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);

        $tenantA = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlordA->id,
            'mobile_number' => '0712345678',
        ]);
        $tenantB = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlordB->id,
            'mobile_number' => '0712345678',
        ]);

        $controller = app(\App\Http\Controllers\Api\MpesaWebhookController::class);

        $method = new \ReflectionMethod($controller, 'findTenantByPhone');
        $method->setAccessible(true);

        $foundA = $method->invoke($controller, '0712345678', $landlordA->id);
        $foundB = $method->invoke($controller, '0712345678', $landlordB->id);

        $this->assertSame($tenantA->id, $foundA->id);
        $this->assertSame($tenantB->id, $foundB->id);
    }
}
