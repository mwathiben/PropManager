<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\DraftPurchaseOrder;
use App\Models\Part;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-54 PARTS-REORDER-1/2/3 watchdog.
 */
class Phase54PartsReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_purchase_orders_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('draft_purchase_orders'));
        $this->assertTrue(Schema::hasTable('draft_purchase_order_lines'));
        $this->assertTrue(Schema::hasColumn('draft_purchase_orders', 'suggested_vendor_id'));
        $this->assertTrue(Schema::hasColumn('draft_purchase_order_lines', 'cost_per_unit_cents_snapshot'));
    }

    public function test_cron_creates_one_order_per_landlord_vendor_pair(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $vendor = Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme',
            'email' => 'acme@vendors.test',
            'phone' => '0712345678',
            'is_active' => true,
        ]);
        $partA = Part::create([
            'landlord_id' => $landlord->id,
            'name' => 'O-ring 12mm',
            'cost_per_unit_cents' => 5000,
            'qty_available' => 1,
            'reorder_threshold' => 5,
            'is_active' => true,
        ]);
        $partB = Part::create([
            'landlord_id' => $landlord->id,
            'name' => 'Pipe joint',
            'cost_per_unit_cents' => 30000,
            'qty_available' => 0,
            'reorder_threshold' => 2,
            'is_active' => true,
        ]);

        // Wire the vendor to one part via a real ticket_parts row.
        $ticket = $this->makeTicketForLandlord($landlord, $vendor);
        \DB::table('ticket_parts')->insert([
            'ticket_id' => $ticket->id,
            'part_id' => $partA->id,
            'qty_used' => 1,
            'cost_allocated_cents' => 5000,
            'recorded_by' => $landlord->id,
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('parts:reorder-suggest')->assertSuccessful();

        $orders = DraftPurchaseOrder::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlord->id)
            ->get();

        // Part A (linked to vendor) + Part B (no vendor) → 2 orders.
        $this->assertSame(2, $orders->count());
        $this->assertNotNull(
            $orders->firstWhere('suggested_vendor_id', $vendor->id),
            'Part with ticket history must group under that vendor.',
        );
        $this->assertNotNull(
            $orders->firstWhere('suggested_vendor_id', null),
            'Part without history must land in the no-vendor bucket.',
        );
    }

    public function test_cron_is_idempotent_across_runs(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Part::create([
            'landlord_id' => $landlord->id,
            'name' => 'Bolt set',
            'cost_per_unit_cents' => 1000,
            'qty_available' => 0,
            'reorder_threshold' => 10,
            'is_active' => true,
        ]);

        $this->artisan('parts:reorder-suggest')->assertSuccessful();
        $this->artisan('parts:reorder-suggest')->assertSuccessful();

        $this->assertSame(
            1,
            DraftPurchaseOrder::withoutGlobalScope('landlord')
                ->where('landlord_id', $landlord->id)
                ->count(),
            'Re-running the cron must not duplicate the open draft.',
        );
    }

    public function test_suggested_qty_is_threshold_times_two_minus_on_hand(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Part::create([
            'landlord_id' => $landlord->id,
            'name' => 'Filter',
            'cost_per_unit_cents' => 7500,
            'qty_available' => 2,
            'reorder_threshold' => 10,
            'is_active' => true,
        ]);

        $this->artisan('parts:reorder-suggest')->assertSuccessful();

        $order = DraftPurchaseOrder::withoutGlobalScope('landlord')->firstOrFail();
        $line = $order->lines()->firstOrFail();
        $this->assertSame(18, (int) $line->qty_suggested); // 10*2 - 2
        $this->assertSame(7500, (int) $line->cost_per_unit_cents_snapshot);
    }

    public function test_cron_is_scheduled_at_six_forty_five_africa_nairobi(): void
    {
        $events = collect(\Illuminate\Support\Facades\Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'parts:reorder-suggest'));

        $this->assertNotNull($entry, 'parts:reorder-suggest is not scheduled.');
        $this->assertSame('45 6 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_landlord_can_confirm_their_own_order(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $order = DraftPurchaseOrder::create([
            'landlord_id' => $landlord->id,
            'suggested_vendor_id' => null,
            'status' => DraftPurchaseOrder::STATUS_DRAFT,
        ]);

        $this->actingAs($landlord)
            ->post(route('parts.purchase-orders.confirm', $order->id))
            ->assertRedirect(route('parts.purchase-orders.index'));

        $this->assertSame(DraftPurchaseOrder::STATUS_SENT, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->sent_at);
    }

    public function test_landlord_cannot_confirm_another_landlords_order(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $order = DraftPurchaseOrder::create([
            'landlord_id' => $landlordB->id,
            'suggested_vendor_id' => null,
            'status' => DraftPurchaseOrder::STATUS_DRAFT,
        ]);

        $this->actingAs($landlordA)
            ->post(route('parts.purchase-orders.confirm', $order->id))
            ->assertForbidden();
    }

    private function makeTicketForLandlord(User $landlord, Vendor $vendor): Ticket
    {
        $property = \App\Models\Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = \App\Models\Building::factory()->create([
            'landlord_id' => $landlord->id,
            'property_id' => $property->id,
        ]);

        return Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'priority' => 'medium',
            'status' => 'open',
            'title' => 'Test',
            'description' => 'Test',
            'vendor_id' => $vendor->id,
        ]);
    }
}
