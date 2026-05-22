<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterProductionCost;
use App\Models\WaterReading;
use App\Services\Water\WaterIntelligenceService;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-91 WATER-HUB-INTELLIGENCE: consumption trends + delta + projection, leak
 * signals (anomalies + main-vs-sub non-revenue water), top consumers, billing-vs-
 * collection, the production-cost margin, and the landlord-only intelligence tab.
 */
class Phase91WaterIntelligenceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    private $building;

    private WaterIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];
        $this->building = $setup['building'];
        $this->building->update(['water_billing_type' => 'consumption', 'water_unit_rate' => 150]);

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);

        $this->service = app(WaterIntelligenceService::class);
    }

    private function reading(Unit $unit, string $date, float $consumption, array $extra = []): WaterReading
    {
        return Model::withoutEvents(fn () => WaterReading::factory()->forUnit($unit)->create(array_merge([
            'version' => 1,
            'reading_date' => $date,
            'previous_reading' => 0,
            'current_reading' => $consumption,
            'consumption' => $consumption,
            'cost' => 0,
            'status' => 'approved',
            'is_invoiced' => false,
        ], $extra)));
    }

    private function meterReading(Meter $meter, string $date, float $consumption): WaterReading
    {
        return Model::withoutEvents(fn () => WaterReading::factory()->forMeter($meter)->create([
            'version' => 1,
            'reading_date' => $date,
            'previous_reading' => 0,
            'current_reading' => $consumption,
            'consumption' => $consumption,
            'cost' => 0,
            'status' => 'approved',
            'is_invoiced' => false,
        ]));
    }

    private function waterInvoice(int $leaseId, float $waterDue, float $totalDue, float $paid, InvoiceStatus $status, string $num): void
    {
        Model::withoutEvents(fn () => Invoice::create([
            'lease_id' => $leaseId,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => $num,
            'due_date' => now(),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => $totalDue - $waterDue,
            'water_due' => $waterDue,
            'arrears' => 0,
            'total_due' => $totalDue,
            'amount_paid' => $paid,
            'status' => $status,
        ]));
    }

    // --- TRENDS / DELTA / PROJECTION -------------------------------------

    public function test_consumption_trend_delta_and_projection(): void
    {
        $unit = $this->units->get(0);
        // Partial CURRENT month — must NOT influence delta/projection/avg.
        $this->reading($unit, now()->toDateString(), 999);
        // Last complete month (300) and the prior complete month (200).
        $this->reading($unit, now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 100);
        $this->reading($unit, now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 200);
        $this->reading($unit, now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(), 200);

        $out = $this->service->forLandlord($this->landlord->id);

        $this->assertCount(12, $out['trend']);
        $this->assertSame(999, end($out['trend'])['value']);
        // Delta compares the two latest COMPLETE months: (300 - 200) / 200 = 50%.
        $this->assertSame(50.0, $out['summary']['period_delta_pct']);
        $this->assertSame(250, $out['summary']['avg_monthly_consumption']);
        // Projection = mean of the trailing non-zero complete months (200, 300).
        $this->assertSame(250, $out['summary']['projection_next']);
    }

    public function test_projection_null_without_enough_history(): void
    {
        // Only one month of real data -> projection is unknown, not fabricated.
        $this->reading($this->units->get(0), now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 300);

        $out = $this->service->forLandlord($this->landlord->id);

        $this->assertNull($out['summary']['projection_next']);
    }

    // --- TOP CONSUMERS ---------------------------------------------------

    public function test_top_consumers_ranked(): void
    {
        $this->reading($this->units->get(0), now()->toDateString(), 500);
        $this->reading($this->units->get(1), now()->toDateString(), 100);

        $top = $this->service->forLandlord($this->landlord->id)['top_consumers'];

        $this->assertNotEmpty($top);
        $this->assertSame(500, $top[0]['consumption']);
        $this->assertGreaterThanOrEqual($top[1]['consumption'] ?? 0, $top[0]['consumption']);
    }

    // --- LEAK SIGNALS: anomalies -----------------------------------------

    public function test_anomalous_reading_is_surfaced(): void
    {
        $this->reading($this->units->get(0), now()->toDateString(), 900, ['is_anomalous' => true]);

        $out = $this->service->forLandlord($this->landlord->id);

        $this->assertSame(1, $out['summary']['anomaly_count']);
        $this->assertNotEmpty($out['anomalies']);
        $this->assertSame(900, $out['anomalies'][0]['consumption']);
    }

    // --- LEAK SIGNALS: main-vs-sub non-revenue water ---------------------

    public function test_non_revenue_water_main_vs_sub(): void
    {
        $unitA = $this->units->get(0);
        $unitB = $this->units->get(1);
        $main = Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'unit_id' => $unitA->id,
            'serial_number' => 'MAIN-1',
            'status' => 'active',
        ]);
        $sub = Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'unit_id' => $unitB->id,
            'parent_meter_id' => $main->id,
            'status' => 'active',
        ]);

        $this->meterReading($main, now()->toDateString(), 1000);
        $this->meterReading($sub, now()->toDateString(), 700);

        $nrw = $this->service->forLandlord($this->landlord->id)['non_revenue_water'];

        $this->assertCount(1, $nrw);
        $this->assertTrue($nrw[0]['complete']);
        $this->assertSame(1000, $nrw[0]['main']);
        $this->assertSame(700, $nrw[0]['sub']);
        $this->assertSame(300, $nrw[0]['loss']);
        $this->assertSame(30.0, $nrw[0]['loss_pct']);
    }

    public function test_non_revenue_water_incomplete_when_sub_unread(): void
    {
        $main = Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'unit_id' => $this->units->get(0)->id,
            'serial_number' => 'MAIN-2',
            'status' => 'active',
        ]);
        Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'unit_id' => $this->units->get(1)->id,
            'parent_meter_id' => $main->id,
            'status' => 'active',
        ]);
        // Main is read but the sub-meter is NOT — loss must NOT read as 100%.
        $this->meterReading($main, now()->toDateString(), 1000);

        $nrw = $this->service->forLandlord($this->landlord->id)['non_revenue_water'];

        $this->assertCount(1, $nrw);
        $this->assertFalse($nrw[0]['complete']);
        $this->assertNull($nrw[0]['loss']);
        $this->assertNull($nrw[0]['loss_pct']);
    }

    public function test_non_revenue_water_empty_without_hierarchy(): void
    {
        $this->reading($this->units->get(0), now()->toDateString(), 100);

        $this->assertSame([], $this->service->forLandlord($this->landlord->id)['non_revenue_water']);
    }

    // --- BILLING VS COLLECTION -------------------------------------------

    public function test_billing_vs_collection(): void
    {
        ['lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $this->units->get(0)));
        // Fully paid: water 1000 of 2000 total -> 1000 collected pro-rata.
        $this->waterInvoice($lease->id, 1000, 2000, 2000, InvoiceStatus::Paid, 'INV-B1');
        // Unpaid: water 500 -> 0 collected.
        $this->waterInvoice($lease->id, 500, 1000, 0, InvoiceStatus::Overdue, 'INV-B2');

        $billing = $this->service->forLandlord($this->landlord->id)['billing'];

        $this->assertSame(1500.0, $billing['billed']);
        $this->assertSame(1000.0, $billing['collected']);
        $this->assertSame(500.0, $billing['outstanding']);
        $this->assertSame(66.7, $billing['collection_rate_pct']);

        // No production costs logged here -> margin is unknown, not a fake 100%.
        $production = $this->service->forLandlord($this->landlord->id)['production'];
        $this->assertFalse($production['costs_logged']);
        $this->assertNull($production['margin']);
        $this->assertNull($production['margin_pct']);
    }

    // --- PRODUCTION COST + MARGIN ----------------------------------------

    public function test_production_cost_margin(): void
    {
        ['lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $this->units->get(0)));
        $this->waterInvoice($lease->id, 1000, 1000, 0, InvoiceStatus::Overdue, 'INV-P1');
        WaterProductionCost::factory()->create([
            'landlord_id' => $this->landlord->id,
            'cost_date' => now()->toDateString(),
            'amount' => 400,
        ]);

        $production = $this->service->forLandlord($this->landlord->id)['production'];

        $this->assertTrue($production['costs_logged']);
        $this->assertSame(1000.0, $production['revenue']);
        $this->assertSame(400.0, $production['cost']);
        $this->assertSame(600.0, $production['margin']);
        $this->assertSame(60.0, $production['margin_pct']);
    }

    // --- PRODUCTION COST: capture authorization --------------------------

    public function test_landlord_logs_a_production_cost(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->post(route('water.production-costs.store'), [
                'cost_date' => now()->toDateString(),
                'amount' => 1234.56,
                'category' => 'electricity',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('water_production_costs', [
            'landlord_id' => $this->landlord->id,
            'category' => 'electricity',
            'amount' => 1234.56,
        ]);
    }

    public function test_caretaker_cannot_log_a_production_cost(): void
    {
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $this->actingAs($caretaker->fresh())
            ->post(route('water.production-costs.store'), [
                'cost_date' => now()->toDateString(),
                'amount' => 100,
                'category' => 'electricity',
            ])
            ->assertForbidden();
    }

    public function test_landlord_deletes_a_production_cost(): void
    {
        $cost = WaterProductionCost::factory()->create(['landlord_id' => $this->landlord->id]);

        $this->actingAs($this->landlord->fresh())
            ->delete(route('water.production-costs.destroy', $cost->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('water_production_costs', ['id' => $cost->id]);
    }

    // --- INTELLIGENCE TAB: role gate -------------------------------------

    public function test_landlord_opens_the_intelligence_tab(): void
    {
        $props = $this->actingAs($this->landlord->fresh())
            ->get(route('water.hub', ['tab' => 'intelligence']))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('intelligence', $props['activeTab']);
        $this->assertArrayHasKey('intelligence', $props);
        $this->assertArrayHasKey('summary', $props['intelligence']);
    }

    public function test_caretaker_cannot_open_the_intelligence_tab(): void
    {
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $props = $this->actingAs($caretaker->fresh())
            ->get(route('water.hub', ['tab' => 'intelligence']))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('overview', $props['activeTab']);
        $this->assertArrayNotHasKey('intelligence', $props);
    }
}
