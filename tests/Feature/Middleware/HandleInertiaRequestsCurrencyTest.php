<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Enums\Currency;
use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class HandleInertiaRequestsCurrencyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_receives_configured_currency_in_shared_props(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        PaymentConfiguration::factory()->forLandlord($setup['landlord'])->create([
            'default_currency' => Currency::USD,
        ]);

        $response = $this->actingAs($setup['landlord'])->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currency.code', 'USD')
            ->where('currency.symbol', '$')
        );
    }

    public function test_caretaker_receives_landlord_currency_in_shared_props(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        PaymentConfiguration::factory()->forLandlord($setup['landlord'])->create([
            'default_currency' => Currency::GBP,
        ]);
        $caretaker = $this->createCaretakerForLandlord($setup['landlord'], $setup['building']);

        $response = $this->actingAs($caretaker)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currency.code', 'GBP')
            ->where('currency.symbol', '£')
        );
    }

    public function test_tenant_receives_landlord_currency_in_shared_props(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        PaymentConfiguration::factory()->forLandlord($setup['landlord'])->create([
            'default_currency' => Currency::EUR,
        ]);
        $tenantData = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $response = $this->actingAs($tenantData['tenant'])->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currency.code', 'EUR')
            ->where('currency.symbol', '€')
        );
    }

    public function test_super_admin_receives_default_kes_currency(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currency.code', 'KES')
            ->where('currency.symbol', 'KSh')
        );
    }

    public function test_unauthenticated_user_gets_null_currency(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currency', null)
        );
    }

    public function test_landlord_without_payment_config_gets_default_kes(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        $response = $this->actingAs($setup['landlord'])->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currency.code', 'KES')
            ->where('currency.symbol', 'KSh')
        );
    }
}
