<?php

namespace Tests\Feature;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaEnvironmentIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mpesa_environment_loaded_from_payment_configuration(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $config = PaymentConfiguration::factory()
            ->forLandlord($landlord)
            ->withMpesa()
            ->create(['mpesa_environment' => 'production']);

        $service = new MpesaService;
        $service->withConfig($config);

        $reflection = new \ReflectionProperty(MpesaService::class, 'environment');
        $this->assertEquals('production', $reflection->getValue($service));

        $reflectionUrl = new \ReflectionProperty(MpesaService::class, 'baseUrl');
        $this->assertStringContainsString('api.safaricom.co.ke', $reflectionUrl->getValue($service));
    }

    public function test_mpesa_environment_defaults_to_sandbox_when_null(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $config = PaymentConfiguration::factory()
            ->forLandlord($landlord)
            ->withMpesa()
            ->create(['mpesa_environment' => null]);

        $service = new MpesaService;
        $service->withConfig($config);

        $reflection = new \ReflectionProperty(MpesaService::class, 'environment');
        $this->assertEquals('sandbox', $reflection->getValue($service));

        $reflectionUrl = new \ReflectionProperty(MpesaService::class, 'baseUrl');
        $this->assertStringContainsString('sandbox.safaricom.co.ke', $reflectionUrl->getValue($service));
    }
}
