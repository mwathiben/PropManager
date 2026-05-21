<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Enums\TicketStatus;
use App\Models\Building;
use App\Models\Ticket;
use App\Models\TicketCost;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Maintenance\TicketCostService;
use App\Services\Vendors\VendorPerformanceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-75 VENDOR-PERF: landlord-side vendor comparison — within-SLA %, cost
 * per ticket, isolation from other landlords' vendors.
 */
class Phase75VendorPerformanceTest extends TestCase
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

    private function vendor(User $owner, string $name): Vendor
    {
        return Vendor::create([
            'landlord_id' => $owner->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function resolvedTicket(User $owner, Building $building, Vendor $vendor, Carbon $resolvedAt, Carbon $dueAt, int $costCents): void
    {
        // withoutEvents: skip the TicketObserver (which would recompute
        // resolution_due_at) + OnboardingMilestoneRecorder (which firstOrFails
        // an onboarding row absent in this fixture) + the TicketCost create
        // path which records a maintenance-cost milestone.
        Model::withoutEvents(function () use ($owner, $building, $vendor, $resolvedAt, $dueAt, $costCents) {
            $ticket = Ticket::create([
                'landlord_id' => $owner->id,
                'building_id' => $building->id,
                'reporter_id' => $owner->id,
                'category' => 'issue',
                'subcategory' => 'plumbing',
                'title' => 'Leak',
                'description' => 'X',
                'priority' => 'high',
                'status' => TicketStatus::Resolved->value,
                'vendor_id' => $vendor->id,
            ]);

            $ticket->forceFill([
                'created_at' => $resolvedAt->copy()->subDay(),
                'resolved_at' => $resolvedAt,
                'resolution_due_at' => $dueAt,
            ])->saveQuietly();

            app(TicketCostService::class)->recordCost($ticket, TicketCost::CATEGORY_VENDOR, $costCents, null, $owner);
        });
    }

    public function test_within_sla_and_cost_per_ticket_are_computed(): void
    {
        $vendorA = $this->vendor($this->landlord, 'Acme Plumbing');
        // Two tickets, both resolved within SLA (resolved_at <= due), total 40000c.
        $this->resolvedTicket($this->landlord, $this->building, $vendorA, now()->subDays(3), now()->subDays(2), 25000);
        $this->resolvedTicket($this->landlord, $this->building, $vendorA, now()->subDays(5), now()->subDays(4), 15000);

        $vendorB = $this->vendor($this->landlord, 'Slow Co');
        // One ticket resolved AFTER due → breached.
        $this->resolvedTicket($this->landlord, $this->building, $vendorB, now()->subDay(), now()->subDays(2), 30000);

        $rows = collect(app(VendorPerformanceService::class)->forLandlord((int) $this->landlord->id));

        $a = $rows->firstWhere('vendor_id', $vendorA->id);
        $this->assertSame(2, $a['resolved_count']);
        $this->assertSame(100.0, $a['within_sla_pct']);
        $this->assertSame(40000, $a['cost_total_cents']);
        $this->assertSame(20000, $a['cost_per_ticket_cents']);

        $b = $rows->firstWhere('vendor_id', $vendorB->id);
        $this->assertSame(0.0, $b['within_sla_pct']);
        $this->assertSame(30000, $b['cost_per_ticket_cents']);
    }

    public function test_does_not_include_another_landlords_vendors(): void
    {
        $mine = $this->vendor($this->landlord, 'Mine');

        // withoutEvents: this runs while acting as $this->landlord, so the 2nd
        // landlord's milestone observers would mis-attribute via TenantScope.
        $other = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup());
        // Act as the other landlord so TenantScope stamps THEIR id on the vendor.
        $this->actingAs($other['landlord']);
        $theirs = $this->vendor($other['landlord'], 'Theirs');
        $this->resolvedTicket($other['landlord'], $other['building'], $theirs, now()->subDays(2), now()->subDay(), 9000);
        $this->actingAs($this->landlord);

        $rows = collect(app(VendorPerformanceService::class)->forLandlord((int) $this->landlord->id));

        $this->assertNotNull($rows->firstWhere('vendor_id', $mine->id));
        $this->assertNull($rows->firstWhere('vendor_id', $theirs->id));
    }

    public function test_page_renders_for_the_landlord(): void
    {
        $this->vendor($this->landlord, 'Acme');

        $this->get(route('maintenance.vendor-performance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Maintenance/VendorPerformance')->has('vendors', 1));
    }
}
