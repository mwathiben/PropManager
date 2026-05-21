<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Enums\TicketStatus;
use App\Models\Building;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Maintenance\VendorAssignmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-75 VENDOR-ROUTING: vendor specialties (allow-list gated), pool
 * suggestion (specialty filter + performance rank), and opt-in auto-route.
 */
class Phase75VendorRoutingTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
        $this->actingAs($this->landlord);
    }

    private function vendor(string $name, array $specialties = []): Vendor
    {
        $vendor = Vendor::create(['landlord_id' => $this->landlord->id, 'name' => $name, 'is_active' => true]);
        $vendor->syncSpecialties($specialties);

        return $vendor;
    }

    private function openTicket(string $subcategory): Ticket
    {
        return Model::withoutEvents(fn () => Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->landlord->id,
            'category' => 'issue',
            'subcategory' => $subcategory,
            'title' => 'Job',
            'description' => 'X',
            'priority' => 'high',
            'status' => TicketStatus::Open->value,
        ]));
    }

    public function test_sync_specialties_gates_to_the_allow_list(): void
    {
        $vendor = $this->vendor('Acme', ['plumbing', 'electrical', 'bogus_trade']);

        $this->assertEqualsCanonicalizing(
            ['plumbing', 'electrical'],
            $vendor->specialties()->pluck('category')->all(),
        );
    }

    public function test_sync_specialties_replaces_existing(): void
    {
        $vendor = $this->vendor('Acme', ['plumbing', 'electrical']);
        $vendor->syncSpecialties(['electrical', 'painting']);

        $this->assertEqualsCanonicalizing(
            ['electrical', 'painting'],
            $vendor->fresh()->specialties()->pluck('category')->all(),
        );
    }

    public function test_suggest_pool_filters_by_specialty(): void
    {
        $plumber = $this->vendor('Plumber', ['plumbing']);
        $this->vendor('Electrician', ['electrical']);

        $pool = app(VendorAssignmentService::class)->suggestPool($this->openTicket('plumbing'));

        $this->assertCount(1, $pool);
        $this->assertSame($plumber->id, $pool->first()['vendor_id']);
        $this->assertTrue($pool->first()['matched']);
    }

    public function test_suggest_pool_falls_back_to_all_active_when_none_match(): void
    {
        $this->vendor('Generalist A', []);
        $this->vendor('Generalist B', ['electrical']);

        $pool = app(VendorAssignmentService::class)->suggestPool($this->openTicket('pest_control'));

        $this->assertCount(2, $pool);
        $this->assertFalse($pool->first()['matched']);
    }

    public function test_auto_assign_is_opt_in(): void
    {
        $this->vendor('Plumber', ['plumbing']);
        $ticket = $this->openTicket('plumbing');

        config(['maintenance.auto_route_vendors' => false]);
        $this->assertNull(app(VendorAssignmentService::class)->autoAssign($ticket));
        $this->assertNull($ticket->fresh()->vendor_id);

        config(['maintenance.auto_route_vendors' => true]);
        $result = app(VendorAssignmentService::class)->autoAssign($ticket);
        $this->assertNotNull($result);
        $this->assertNotNull($ticket->fresh()->vendor_id);
    }

    public function test_auto_assign_never_overrides_a_manual_assignment(): void
    {
        $manual = $this->vendor('Chosen', ['plumbing']);
        $this->vendor('Other', ['plumbing']);
        $ticket = $this->openTicket('plumbing');
        $ticket->forceFill(['vendor_id' => $manual->id])->saveQuietly();

        config(['maintenance.auto_route_vendors' => true]);
        $this->assertNull(app(VendorAssignmentService::class)->autoAssign($ticket));
        $this->assertSame($manual->id, $ticket->fresh()->vendor_id);
    }

    public function test_store_vendor_persists_specialties(): void
    {
        $this->post(route('finances.vendors.store'), [
            'name' => 'New Vendor',
            'specialties' => ['plumbing', 'appliances'],
        ])->assertRedirect();

        $vendor = Vendor::where('landlord_id', $this->landlord->id)->where('name', 'New Vendor')->firstOrFail();
        $this->assertEqualsCanonicalizing(['plumbing', 'appliances'], $vendor->specialties()->pluck('category')->all());
    }

    public function test_get_vendors_payload_includes_specialties(): void
    {
        $vendor = $this->vendor('Acme', ['plumbing']);

        $rows = app(\App\Services\FinanceFilterService::class)->getVendors((int) $this->landlord->id);
        $row = collect($rows)->firstWhere('id', $vendor->id);

        $this->assertSame(['plumbing'], $row['specialties']);
    }

    public function test_updating_vendor_persists_specialties(): void
    {
        $vendor = $this->vendor('Acme', ['plumbing']);

        $this->put(route('finances.vendors.update', $vendor->id), [
            'name' => 'Acme',
            'specialties' => ['plumbing', 'electrical'],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(['plumbing', 'electrical'], $vendor->fresh()->specialties()->pluck('category')->all());
    }
}
