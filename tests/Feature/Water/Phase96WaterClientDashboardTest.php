<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Meter;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterReading;
use App\Services\Water\WaterAccountService;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-96 WATER-CLIENT-DASHBOARD: the connection/meter-centric WaterAccountService
 * path + the enriched water-client dashboard reusing the shared Components/Water/*.
 */
class Phase96WaterClientDashboardTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Unit $unit;

    private WaterAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->unit = $setup['units']->first();
        $setup['building']->update(['water_billing_type' => 'consumption', 'water_unit_rate' => 150]);

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
            'supplies_water_clients' => true,
            'water_client_rate' => 200,
        ]);
        WaterModuleAccess::forget($this->landlord->id);

        $this->service = app(WaterAccountService::class);
    }

    private function meter(array $extra = []): Meter
    {
        return Meter::factory()->create(array_merge([
            'landlord_id' => $this->landlord->id,
            'unit_id' => $this->unit->id,
        ], $extra));
    }

    private function client(): User
    {
        return Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'water_client',
            'landlord_id' => $this->landlord->id,
            'email_verified_at' => now(),
        ]));
    }

    private function connection(array $extra = []): WaterConnection
    {
        return WaterConnection::factory()->create(array_merge([
            'landlord_id' => $this->landlord->id,
        ], $extra));
    }

    private function reading(Meter $meter, float $consumption, ?string $date = null, array $extra = []): WaterReading
    {
        return Model::withoutEvents(fn () => WaterReading::factory()->forMeter($meter)->create(array_merge([
            'version' => 1,
            'reading_date' => $date ?? now()->toDateString(),
            'previous_reading' => 0,
            'current_reading' => $consumption,
            'consumption' => $consumption,
            'cost' => $consumption * 150,
            'status' => 'approved',
            'is_invoiced' => false,
        ], $extra)));
    }

    // --- SERVICE: connection/meter-centric overview ----------------------

    public function test_overview_for_connection_aggregates_meter_consumption(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'connected_at' => now()->subMonths(6)->toDateString()]);

        $this->reading($meter, 30, now()->subMonth()->toDateString());
        $this->reading($meter, 45, now()->toDateString());

        $overview = $this->service->overviewForConnection($connection);

        $this->assertCount(12, $overview['history']);
        $this->assertSame(45, $overview['summary']['latest_consumption']);
        $this->assertSame(75, array_sum(array_column($overview['history'], 'value')));
        $this->assertSame([], $overview['charges']); // Phase 97 introduces charges.
    }

    public function test_overview_excludes_readings_before_the_connection_started(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'connected_at' => now()->subMonths(2)->toDateString()]);

        // A reading from when this meter served a prior occupant — must NOT leak.
        $this->reading($meter, 999, now()->subMonths(4)->toDateString());
        $this->reading($meter, 40, now()->subMonth()->toDateString());

        $overview = $this->service->overviewForConnection($connection);

        $this->assertSame(40, $overview['summary']['latest_consumption']);
        $this->assertNotContains(999, array_column($overview['history'], 'value'));
    }

    public function test_overview_surfaces_anomaly_leak_alert(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'connected_at' => now()->subMonths(3)->toDateString()]);

        $this->reading($meter, 500, now()->toDateString(), ['is_anomalous' => true]);

        $overview = $this->service->overviewForConnection($connection);

        $this->assertNotNull($overview['alert']);
        $this->assertSame(500, $overview['alert']['consumption']);
    }

    public function test_overview_reports_meter_disconnection(): void
    {
        $meter = $this->meter(['disconnected_at' => now(), 'disconnect_reason' => 'Arrears']);
        $connection = $this->connection(['meter_id' => $meter->id]);

        $overview = $this->service->overviewForConnection($connection);

        $this->assertTrue($overview['disconnection']['disconnected']);
        $this->assertSame('Arrears', $overview['disconnection']['reason']);
    }

    public function test_overview_does_not_leak_a_decommissioned_meters_history(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id]);
        $this->reading($meter, 80, now()->toDateString());

        // The meter is decommissioned (soft-deleted) after the line was provisioned.
        $meter->delete();

        $overview = $this->service->overviewForConnection($connection->fresh());

        // No readable meter -> empty account, never the decommissioned meter's data.
        $this->assertNull($overview['summary']['latest_consumption']);
        $this->assertSame(0, array_sum(array_column($overview['history'], 'value')));
        $this->assertFalse($overview['disconnection']['disconnected']);
    }

    public function test_overview_is_graceful_for_a_connection_without_a_meter(): void
    {
        $connection = $this->connection(['meter_id' => null, 'billing_mode' => 'flat_rate']);

        $overview = $this->service->overviewForConnection($connection);

        $this->assertCount(12, $overview['history']);
        $this->assertSame(0, array_sum(array_column($overview['history'], 'value')));
        $this->assertNull($overview['summary']['latest_consumption']);
        $this->assertNull($overview['alert']);
        $this->assertFalse($overview['disconnection']['disconnected']);
    }

    public function test_unit_centric_overview_still_works(): void
    {
        // Phase-93 path must be unchanged by the refactor.
        $meter = $this->meter();
        $this->reading($meter, 25, now()->toDateString());

        $overview = $this->service->overview($this->unit->id, null, now()->subYear()->toDateString());

        $this->assertSame(25, $overview['summary']['latest_consumption']);
    }

    // --- DASHBOARD payload -----------------------------------------------

    public function test_dashboard_payload_carries_enriched_connections(): void
    {
        $client = $this->client();
        $meter = $this->meter();
        $this->connection([
            'user_id' => $client->id,
            'meter_id' => $meter->id,
            'identifier' => 'LINE-WC-9',
            'client_rate' => 250,
            'connected_at' => now()->subMonths(3)->toDateString(),
        ]);
        $this->reading($meter, 60, now()->toDateString());

        $props = $this->actingAs($client->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('WaterClient/Dashboard', $props['component']);
        $conn = $props['props']['connections'][0];
        $this->assertSame('LINE-WC-9', $conn['identifier']);
        $this->assertTrue($conn['has_meter']);
        $this->assertSame(250.0, $conn['effective_rate']);
        $this->assertSame(60, $conn['summary']['latest_consumption']);
        $this->assertCount(12, $conn['history']);
    }

    public function test_dashboard_falls_back_to_landlord_default_rate(): void
    {
        $client = $this->client();
        $this->connection(['user_id' => $client->id, 'meter_id' => $this->meter()->id, 'client_rate' => null]);

        $props = $this->actingAs($client->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('page');

        // client_rate null → landlord's water_client_rate (200) from setUp.
        $this->assertSame(200.0, $props['props']['connections'][0]['effective_rate']);
    }

    public function test_dashboard_lists_multiple_water_lines(): void
    {
        $client = $this->client();
        $this->connection(['user_id' => $client->id, 'meter_id' => $this->meter()->id]);
        $this->connection(['user_id' => $client->id, 'meter_id' => $this->meter()->id]);

        $props = $this->actingAs($client->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('page');

        $this->assertCount(2, $props['props']['connections']);
    }
}
