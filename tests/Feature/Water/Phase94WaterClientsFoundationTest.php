<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Meter;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WaterConnection;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-94 WATER-CLIENTS-FOUNDATION: the WaterConnection model, the water_client
 * role plumbing, and the landlord-only setup + connection management surface.
 */
class Phase94WaterClientsFoundationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $building;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
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
    }

    // --- SETUP -----------------------------------------------------------

    public function test_landlord_enables_water_clients(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->put(route('water.clients.setup'), ['supplies_water_clients' => true, 'water_client_rate' => 120])
            ->assertRedirect();

        $config = PaymentConfiguration::where('landlord_id', $this->landlord->id)->firstOrFail();
        $this->assertTrue((bool) $config->supplies_water_clients);
        $this->assertEquals(120, (float) $config->water_client_rate);
    }

    // --- CONNECTION CRUD -------------------------------------------------

    public function test_landlord_creates_a_water_connection(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->post(route('water.connections.store'), [
                'identifier' => 'LINE-NORTH-01',
                'client_name' => 'Neighbour A',
                'billing_mode' => 'metered',
                'client_rate' => 150,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('water_connections', [
            'landlord_id' => $this->landlord->id,
            'identifier' => 'LINE-NORTH-01',
            'client_name' => 'Neighbour A',
        ]);
    }

    public function test_connection_meter_must_belong_to_landlord(): void
    {
        $other = $this->createLandlordWithFullSetup();
        $foreignMeter = Meter::factory()->create(['landlord_id' => $other['landlord']->id, 'status' => 'active']);

        $this->actingAs($this->landlord->fresh())
            ->post(route('water.connections.store'), [
                'identifier' => 'LINE-X',
                'billing_mode' => 'metered',
                'status' => 'active',
                'meter_id' => $foreignMeter->id,
            ])
            ->assertSessionHasErrors('meter_id');

        $this->assertDatabaseMissing('water_connections', ['identifier' => 'LINE-X']);
    }

    public function test_landlord_updates_and_deletes_a_connection(): void
    {
        $connection = WaterConnection::factory()->create(['landlord_id' => $this->landlord->id, 'identifier' => 'LINE-OLD']);

        $this->actingAs($this->landlord->fresh())
            ->put(route('water.connections.update', $connection->id), [
                'identifier' => 'LINE-NEW',
                'billing_mode' => 'flat_rate',
                'status' => 'inactive',
            ])
            ->assertRedirect();
        $this->assertSame('LINE-NEW', $connection->fresh()->identifier);

        $this->actingAs($this->landlord->fresh())
            ->delete(route('water.connections.destroy', $connection->id))
            ->assertRedirect();
        $this->assertSoftDeleted('water_connections', ['id' => $connection->id]);
    }

    public function test_cannot_manage_another_landlords_connection(): void
    {
        $other = $this->createLandlordWithFullSetup();
        $foreign = WaterConnection::factory()->create(['landlord_id' => $other['landlord']->id]);

        $response = $this->actingAs($this->landlord->fresh())
            ->delete(route('water.connections.destroy', $foreign->id));

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('water_connections', ['id' => $foreign->id, 'deleted_at' => null]);
    }

    public function test_caretaker_cannot_create_a_connection(): void
    {
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $this->actingAs($caretaker->fresh())
            ->post(route('water.connections.store'), ['identifier' => 'LINE-C', 'billing_mode' => 'metered', 'status' => 'active'])
            ->assertForbidden();
    }

    // --- TAB GATE --------------------------------------------------------

    public function test_landlord_opens_the_clients_tab(): void
    {
        $props = $this->actingAs($this->landlord->fresh())
            ->get(route('water.hub', ['tab' => 'clients']))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('clients', $props['activeTab']);
        $this->assertArrayHasKey('clients', $props);
        $this->assertArrayHasKey('connections', $props['clients']);
    }

    public function test_caretaker_cannot_open_the_clients_tab(): void
    {
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $props = $this->actingAs($caretaker->fresh())
            ->get(route('water.hub', ['tab' => 'clients']))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('overview', $props['activeTab']);
        $this->assertArrayNotHasKey('clients', $props);
    }

    // --- ROLE + SCOPE ----------------------------------------------------

    public function test_water_client_role_helper(): void
    {
        $waterClient = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'water_client',
            'landlord_id' => $this->landlord->id,
        ]));

        $this->assertTrue($waterClient->isWaterClient());
        $this->assertFalse($waterClient->isTenant());
        $this->assertFalse($waterClient->isLandlord());
        // A water client carries their supplier landlord_id (TenantScope keys on it).
        $this->assertSame($this->landlord->id, $waterClient->landlord_id);
    }
}
