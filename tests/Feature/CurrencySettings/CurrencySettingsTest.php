<?php

namespace Tests\Feature\CurrencySettings;

use App\Enums\Currency;
use App\Models\Building;
use App\Models\Lease;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencySettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->property = Property::create([
            'name' => 'Currency Test Property',
            'address' => '1 Test Lane',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->building = Building::create([
            'property_id' => $this->property->id,
            'name' => 'Block A',
            'total_floors' => 1,
            'units_per_floor' => 2,
            'landlord_id' => $this->landlord->id,
            'building_type' => 'residential_apartment',
        ]);
    }

    public function test_payment_configuration_defaults_currency_to_kes(): void
    {
        $config = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash'],
        ]);

        $this->assertEquals(Currency::KES, $config->fresh()->default_currency);
    }

    public function test_payment_configuration_casts_currency_to_enum(): void
    {
        $config = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash'],
            'default_currency' => 'USD',
        ]);

        $fresh = $config->fresh();
        $this->assertInstanceOf(Currency::class, $fresh->default_currency);
        $this->assertEquals(Currency::USD, $fresh->default_currency);
    }

    public function test_building_can_store_currency(): void
    {
        $this->building->update(['currency' => 'GBP']);

        $fresh = $this->building->fresh();
        $this->assertInstanceOf(Currency::class, $fresh->currency);
        $this->assertEquals(Currency::GBP, $fresh->currency);
    }

    public function test_building_inherits_landlord_currency_when_null(): void
    {
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash'],
            'default_currency' => 'USD',
        ]);

        $this->assertNull($this->building->currency);
        $this->assertEquals(Currency::USD, $this->building->getEffectiveCurrency());
    }

    public function test_building_overrides_landlord_currency_when_set(): void
    {
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash'],
            'default_currency' => 'USD',
        ]);

        $this->building->update(['currency' => 'GBP']);

        $this->assertEquals(Currency::GBP, $this->building->fresh()->getEffectiveCurrency());
    }

    public function test_building_falls_back_to_kes_when_no_config_exists(): void
    {
        $this->assertNull(
            PaymentConfiguration::where('landlord_id', $this->landlord->id)->first()
        );
        $this->assertEquals(Currency::KES, $this->building->getEffectiveCurrency());
    }

    public function test_landlord_can_update_default_currency_via_settings(): void
    {
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash'],
        ]);

        $response = $this->actingAs($this->landlord)
            ->post(route('finances.settings.default-currency'), [
                'default_currency' => 'EUR',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $config = PaymentConfiguration::where('landlord_id', $this->landlord->id)->first();
        $this->assertEquals(Currency::EUR, $config->default_currency);
    }

    public function test_invalid_currency_value_is_rejected(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('finances.settings.default-currency'), [
                'default_currency' => 'INVALID',
            ]);

        $response->assertSessionHasErrors('default_currency');
    }

    public function test_invoice_generation_uses_building_effective_currency(): void
    {
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash'],
            'default_currency' => 'USD',
        ]);

        $unit = Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $this->landlord->id,
        ]);

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now()->subMonth(),
            'is_active' => true,
            'wallet_balance' => 0,
        ]);

        $service = app(InvoiceService::class);
        $invoice = $service->generateInvoiceForLease($lease, Carbon::now());

        $this->assertEquals(Currency::USD, $invoice->currency);
    }

    public function test_first_invoice_generation_uses_building_effective_currency(): void
    {
        $this->building->update(['currency' => 'EUR']);

        $unit = Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'A102',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 30000,
            'landlord_id' => $this->landlord->id,
        ]);

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => 30000,
            'deposit_amount' => 30000,
            'start_date' => now(),
            'is_active' => true,
            'wallet_balance' => 0,
        ]);

        $service = app(InvoiceService::class);
        $invoice = $service->generateFirstInvoiceForLease($lease);

        $this->assertEquals(Currency::EUR, $invoice->currency);
    }

    public function test_currency_options_are_passed_to_settings_page(): void
    {
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash'],
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('finances.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('currencyOptions')
            ->where('currencyOptions', Currency::options())
        );
    }

    public function test_building_currency_can_be_updated_via_building_settings(): void
    {
        // Phase-17 MONEY-9: validator pins building currency to KES until
        // Phase-18 FX support ships. The original test used GBP — now
        // expected to be rejected. KES remains accepted.
        $response = $this->actingAs($this->landlord)
            ->put(route('buildings.update-settings', $this->building), [
                'name' => $this->building->name,
                'building_type' => $this->building->building_type,
                'currency' => 'KES',
            ]);

        $response->assertRedirect();

        $this->assertEquals(Currency::KES, $this->building->fresh()->currency);
    }

    public function test_building_currency_update_rejects_non_kes(): void
    {
        // Phase-17 MONEY-9 regression-lock: GBP/USD/EUR rejected by
        // validator. Cross-currency arithmetic is broken without
        // Phase-18 FX support.
        $response = $this->actingAs($this->landlord)
            ->put(route('buildings.update-settings', $this->building), [
                'name' => $this->building->name,
                'building_type' => $this->building->building_type,
                'currency' => 'GBP',
            ]);

        $response->assertSessionHasErrors('currency');
    }
}
