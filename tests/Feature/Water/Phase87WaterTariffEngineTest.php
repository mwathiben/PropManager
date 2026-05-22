<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Invoice;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\InvoiceService;
use App\Services\Water\WaterTariffService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-87 WATER-TARIFF-ENGINE: tiered/block rates + standing charge +
 * sewerage % + VAT % + minimum bill, applied non-destructively over the
 * Phase-86 meter foundation and the live biller.
 */
class Phase87WaterTariffEngineTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    private $building;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];
        $this->building = $setup['building'];
        $this->building->update(['water_billing_type' => 'consumption']);
    }

    // --- TARIFF SERVICE (pure) -------------------------------------------

    public function test_tiered_consumption_charge(): void
    {
        $charge = app(WaterTariffService::class)->computeConsumptionCharge(25, [
            'tiered_tariffs' => [
                ['from' => 0, 'to' => 10, 'rate' => 50],
                ['from' => 10, 'to' => null, 'rate' => 70],
            ],
        ]);

        // 10 units @ 50 + 15 units @ 70 = 500 + 1050
        $this->assertEquals(1550, $charge);
    }

    public function test_flat_fallback_when_no_bands(): void
    {
        $this->assertEquals(1500, app(WaterTariffService::class)->computeConsumptionCharge(10, ['unit_rate' => 150]));
    }

    public function test_assemble_applies_standing_sewerage_and_minimum(): void
    {
        $svc = app(WaterTariffService::class);

        // base 1000 + standing 100 = 1100; + sewerage 10% (110) = 1210
        $this->assertEquals(1210, $svc->assembleWaterCharge(1000, ['standing_charge' => 100, 'sewerage_percent' => 10]));
        // minimum bill floor
        $this->assertEquals(500, $svc->assembleWaterCharge(100, ['minimum_charge' => 500]));
        // VAT on top
        $this->assertEquals(1160, $svc->assembleWaterCharge(1000, ['vat_percent' => 16]));
    }

    public function test_assemble_is_non_destructive_when_unset(): void
    {
        $this->assertEquals(1000.0, app(WaterTariffService::class)->assembleWaterCharge(1000, []));
    }

    // --- BILLING WIRE ----------------------------------------------------

    public function test_reading_cost_is_tiered_on_a_tiered_building(): void
    {
        $this->actingAs($this->landlord->fresh());
        $unit = $this->units->get(0);
        $unit->building->update([
            'tiered_tariffs' => [
                ['from' => 0, 'to' => 10, 'rate' => 50],
                ['from' => 10, 'to' => null, 'rate' => 70],
            ],
        ]);

        // Created directly (not via factory) so the observer runs without the
        // factory's throwaway Unit tripping OnboardingMilestoneRecorder.
        $reading = WaterReading::create([
            'unit_id' => $unit->id,
            'previous_reading' => 0,
            'current_reading' => 25,
            'reading_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->assertEquals(1550, (float) $reading->fresh()->cost);
    }

    public function test_reading_cost_unchanged_on_a_flat_rate_building(): void
    {
        $this->actingAs($this->landlord->fresh());
        $unit = $this->units->get(1);
        $unit->building->update(['water_unit_rate' => 100]); // no bands

        $reading = WaterReading::create([
            'unit_id' => $unit->id,
            'previous_reading' => 0,
            'current_reading' => 10,
            'reading_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->assertEquals(1000, (float) $reading->fresh()->cost);
    }

    public function test_invoice_water_due_applies_standing_and_sewerage(): void
    {
        $this->actingAs($this->landlord->fresh());
        $unit = $this->units->get(2);
        $unit->building->update([
            'water_billing_type' => 'consumption',
            'water_standing_charge' => 100,
            'water_sewerage_percent' => 10,
        ]);

        ['lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $unit));

        Model::withoutEvents(fn () => WaterReading::create([
            'unit_id' => $unit->id,
            'landlord_id' => $this->landlord->id,
            'previous_reading' => 0,
            'current_reading' => 10,
            'consumption' => 10,
            'cost' => 1000,
            'reading_date' => now()->toDateString(),
            'status' => 'approved',
            'is_invoiced' => false,
        ]));

        $invoice = Model::withoutEvents(fn () => app(InvoiceService::class)->generateInvoiceForLease($lease->fresh(), now()));

        // base 1000 + standing 100 = 1100; + sewerage 10% = 1210
        $this->assertEquals(1210, (float) Invoice::find($invoice->id)->water_due);
    }

    public function test_invoice_water_due_unchanged_when_no_tariff_extras(): void
    {
        $this->actingAs($this->landlord->fresh());
        $unit = $this->units->get(3);
        $unit->building->update(['water_billing_type' => 'consumption']);

        ['lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $unit));

        Model::withoutEvents(fn () => WaterReading::create([
            'unit_id' => $unit->id,
            'landlord_id' => $this->landlord->id,
            'previous_reading' => 0,
            'current_reading' => 10,
            'consumption' => 10,
            'cost' => 1500,
            'reading_date' => now()->toDateString(),
            'status' => 'approved',
            'is_invoiced' => false,
        ]));

        $invoice = Model::withoutEvents(fn () => app(InvoiceService::class)->generateInvoiceForLease($lease->fresh(), now()));

        // No extras configured => water_due equals the raw reading sum.
        $this->assertEquals(1500, (float) Invoice::find($invoice->id)->water_due);
    }

    // --- CONFIG -----------------------------------------------------------

    public function test_landlord_persists_tiered_bands_levies_and_source(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->put(route('water.settings.update'), [
                'water_billing_type' => 'consumption',
                'water_unit_rate' => 150,
                'tiered_tariffs' => [
                    ['from' => 0, 'to' => 10, 'rate' => 50],
                    ['from' => 10, 'to' => null, 'rate' => 70],
                ],
                'water_standing_charge' => 100,
                'water_sewerage_percent' => 10,
                'water_source' => 'borehole',
            ])
            ->assertRedirect();

        $config = PaymentConfiguration::where('landlord_id', $this->landlord->id)->firstOrFail();
        $this->assertEquals(100, (float) $config->water_standing_charge);
        $this->assertEquals(10, (float) $config->water_sewerage_percent);
        $this->assertSame('borehole', $config->water_source);
        $this->assertCount(2, $config->tiered_tariffs);
    }
}
