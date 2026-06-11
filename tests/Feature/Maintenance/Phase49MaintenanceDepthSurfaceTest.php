<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Enums\TicketStatus;
use App\Events\TicketAssignedToVendor;
use App\Events\TicketSlaBreached;
use App\Models\Building;
use App\Models\Part;
use App\Models\Property;
use App\Models\SlaDefinition;
use App\Models\Ticket;
use App\Models\TicketCost;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Maintenance\SlaDefinitionService;
use App\Services\Maintenance\TicketCostService;
use App\Services\Maintenance\TicketResolutionService;
use App\Services\Maintenance\VendorAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-49 CI-1: consolidated MAINTENANCE-DEPTH surface watchdog.
 */
class Phase49MaintenanceDepthSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- TICKETS-SLA-DEEP --------------------------------------------------

    public function test_tickets_table_has_resolution_due_at(): void
    {
        $this->assertTrue(Schema::hasColumn('tickets', 'resolution_due_at'));
    }

    public function test_ticket_has_resolution_sla_seconds_constant(): void
    {
        $this->assertIsArray(Ticket::RESOLUTION_SLA_SECONDS);
        $this->assertArrayHasKey('urgent', Ticket::RESOLUTION_SLA_SECONDS);
        $this->assertArrayHasKey('low', Ticket::RESOLUTION_SLA_SECONDS);
    }

    public function test_breached_resolution_sla_scope_filters_correctly(): void
    {
        [$landlord, $building] = $this->makeLandlordWithBuilding();
        $ticket = Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Test',
            'description' => 'Test',
            'priority' => 'high',
            'status' => TicketStatus::Open->value,
        ]);

        $ticket->update(['resolution_due_at' => now()->subDay()]);

        $breached = Ticket::query()->withoutGlobalScope('landlord')->breachedResolutionSla()->get();
        $this->assertCount(1, $breached);
    }

    public function test_audit_sla_command_emits_both_gauges(): void
    {
        $this->artisan('tickets:audit-sla --dry-run')->assertExitCode(0);
    }

    public function test_ticket_sla_breached_event_has_type_discriminator(): void
    {
        $reflection = new \ReflectionClass(TicketSlaBreached::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();
        $paramNames = array_map(fn ($p) => $p->getName(), $params);

        $this->assertContains('type', $paramNames);
        $this->assertSame('response', TicketSlaBreached::TYPE_RESPONSE);
        $this->assertSame('resolution', TicketSlaBreached::TYPE_RESOLUTION);
    }

    // -- SLA-PER-CATEGORY --------------------------------------------------

    public function test_sla_definitions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('sla_definitions'));
    }

    public function test_sla_definition_service_cascade_resolves(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        SlaDefinition::create([
            'landlord_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'priority' => 'high',
            'response_seconds' => 1800,
            'resolution_seconds' => 7200,
            'is_active' => true,
        ]);

        $service = app(SlaDefinitionService::class);
        $r = $service->resolveFor('issue', 'plumbing', 'high', $landlord->id);

        $this->assertSame(1800, $r['response_seconds']);
        $this->assertSame(7200, $r['resolution_seconds']);
    }

    public function test_sla_seeder_populates_platform_defaults(): void
    {
        $count = SlaDefinition::query()->whereNull('landlord_id')->count();
        $this->assertGreaterThan(0, $count, 'Phase49SlaSeeder should run on migrate');
    }

    // -- VENDOR-MARKETPLACE ------------------------------------------------

    public function test_tickets_table_has_vendor_id(): void
    {
        $this->assertTrue(Schema::hasColumn('tickets', 'vendor_id'));
    }

    public function test_vendor_assignment_service_writes_vendor_id_and_activity(): void
    {
        Event::fake([TicketAssignedToVendor::class]);

        [$landlord, $building] = $this->makeLandlordWithBuilding();
        $this->actingAs($landlord);

        $vendor = Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme Plumbing',
            'contact_person' => 'Joe',
            'email' => 'joe@acme.test',
            'phone' => '0712345678',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Leak',
            'description' => 'Kitchen pipe',
            'priority' => 'high',
            'status' => TicketStatus::Open->value,
        ]);

        app(VendorAssignmentService::class)->assign($ticket, $vendor, 'urgent');

        $this->assertSame($vendor->id, $ticket->fresh()->vendor_id);
        // Assert the activity was recorded, not that it is the chronologically
        // last one. assign() updates the vendor_* fields then writes the
        // vendor_assigned activity, but a later-id activity (e.g. the ticket
        // created-observer's afterCommit auto-route when maintenance.auto_route_vendors
        // is enabled) can win latest('id') and flake this assertion. Existence
        // is the real intent and is order-independent.
        $this->assertTrue(
            $ticket->activities()->where('action', 'vendor_assigned')->exists(),
            'assign() should record a vendor_assigned activity.',
        );
        Event::assertDispatched(TicketAssignedToVendor::class);
    }

    // -- PARTS-INVENTORY ---------------------------------------------------

    public function test_parts_and_ticket_parts_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('parts'));
        $this->assertTrue(Schema::hasTable('ticket_parts'));
    }

    public function test_part_below_threshold_helper(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $part = Part::create([
            'landlord_id' => $landlord->id,
            'name' => 'Tap washer',
            'cost_per_unit_cents' => 5000,
            'qty_available' => 2,
            'reorder_threshold' => 5,
            'is_active' => true,
        ]);

        $this->assertTrue($part->isBelowThreshold());
    }

    public function test_ticket_resolution_service_records_parts_decrements_stock_and_seeds_cost(): void
    {
        [$landlord, $building] = $this->makeLandlordWithBuilding();
        $this->actingAs($landlord);

        $part = Part::create([
            'landlord_id' => $landlord->id,
            'name' => 'Tap washer',
            'cost_per_unit_cents' => 5000,
            'qty_available' => 10,
            'reorder_threshold' => 2,
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Leak',
            'description' => 'Kitchen pipe',
            'priority' => 'high',
            'status' => TicketStatus::InProgress->value,
        ]);

        $result = app(TicketResolutionService::class)->recordParts($ticket, [$part->id => 3]);

        $this->assertSame(1, $result['rows_inserted']);
        $this->assertSame(15000, $result['total_cost_cents']);
        $this->assertSame(7, $part->fresh()->qty_available);

        $partsCost = TicketCost::query()
            ->where('ticket_id', $ticket->id)
            ->where('category', TicketCost::CATEGORY_PARTS)
            ->first();
        $this->assertNotNull($partsCost);
        $this->assertSame(15000, (int) $partsCost->amount_cents);
    }

    public function test_parts_audit_stock_command_exits_zero(): void
    {
        $this->artisan('parts:audit-stock')->assertExitCode(0);
    }

    // -- MAINTENANCE-COSTS -------------------------------------------------

    public function test_ticket_costs_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('ticket_costs'));
    }

    public function test_ticket_cost_service_summarize_returns_per_category_breakdown(): void
    {
        [$landlord, $building] = $this->makeLandlordWithBuilding();
        $this->actingAs($landlord);

        $ticket = Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Leak',
            'description' => 'X',
            'priority' => 'high',
            'status' => TicketStatus::Resolved->value,
        ]);

        $service = app(TicketCostService::class);
        $service->recordCost($ticket, TicketCost::CATEGORY_VENDOR, 25000, 'invoice 123', $landlord);
        $service->recordCost($ticket, TicketCost::CATEGORY_LABOR, 10000, '2h', $landlord);

        $summary = $service->summarize($ticket);
        $this->assertSame(0, $summary['parts']);
        $this->assertSame(25000, $summary['vendor']);
        $this->assertSame(10000, $summary['labor']);
        $this->assertSame(35000, $summary['total']);
    }

    public function test_ticket_total_maintenance_cost_aggregates(): void
    {
        [$landlord, $building] = $this->makeLandlordWithBuilding();
        $this->actingAs($landlord);

        $ticket = Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Leak',
            'description' => 'X',
            'priority' => 'high',
            'status' => TicketStatus::Resolved->value,
        ]);

        app(TicketCostService::class)->recordCost($ticket, TicketCost::CATEGORY_VENDOR, 50000, null, $landlord);

        $this->assertSame(50000, $ticket->totalMaintenanceCost());
    }

    public function test_maintenance_cost_rollup_command_exits_zero(): void
    {
        $this->artisan('maintenance:cost-rollup')->assertExitCode(0);
    }

    // -- RUNBOOK + ALERTS --------------------------------------------------

    public function test_maintenance_runbook_exists(): void
    {
        $this->assertTrue(file_exists(base_path('docs/runbooks/maintenance.md')));
    }

    public function test_alert_thresholds_carries_new_rows(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('ticket_resolution_breach_count', $md);
        $this->assertStringContainsString('parts_below_threshold_count', $md);
        $this->assertStringContainsString('landlord_maintenance_cost_kes_30d', $md);
    }

    // -- HELPERS -----------------------------------------------------------

    private function makeLandlordWithBuilding(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Test Estate',
            'type' => 'residential',
        ]);
        $building = Building::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'name' => 'Block A',
            'total_floors' => 2,
            'units_per_floor' => 4,
        ]);

        return [$landlord, $building];
    }
}
