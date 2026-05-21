<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Enums\TicketStatus;
use App\Models\Building;
use App\Models\DraftPurchaseOrderLine;
use App\Models\Part;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Maintenance\PartUsageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-75 PARTS-PREDICT: usage-rate estimation + lead-time-aware reorder
 * (a part above its static threshold but projected to run out within the
 * supplier lead time is ordered early, tagged lead_time_buffer).
 */
class Phase75PartsPredictTest extends TestCase
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

    private function part(int $qty, int $threshold): Part
    {
        return Part::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Pipe',
            'category' => 'plumbing',
            'cost_per_unit_cents' => 1000,
            'qty_available' => $qty,
            'reorder_threshold' => $threshold,
            'is_active' => true,
        ]);
    }

    private function consume(Part $part, int $qtyUsed, int $daysAgo = 1): void
    {
        $ticket = Model::withoutEvents(fn () => Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Job',
            'description' => 'X',
            'priority' => 'high',
            'status' => TicketStatus::Open->value,
        ]));

        DB::table('ticket_parts')->insert([
            'ticket_id' => $ticket->id,
            'part_id' => $part->id,
            'qty_used' => $qtyUsed,
            'cost_allocated_cents' => $qtyUsed * 1000,
            'recorded_at' => now()->subDays($daysAgo),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_daily_rate_is_window_sum_over_days(): void
    {
        $part = $this->part(qty: 50, threshold: 5);
        $this->consume($part, qtyUsed: 90, daysAgo: 10);
        $this->consume($part, qtyUsed: 90, daysAgo: 200); // outside the 90-day window

        $this->assertEqualsWithDelta(1.0, app(PartUsageService::class)->dailyRate($part, 90), 0.0001);
    }

    public function test_part_within_lead_time_buffer_is_suggested_as_predicted(): void
    {
        // threshold 2, on-hand 5 (above static), usage 1/day, default lead time 7 →
        // effective threshold 2 + ceil(7*1) = 9, so 5 <= 9 triggers as lead-time buffer.
        $part = $this->part(qty: 5, threshold: 2);
        $this->consume($part, qtyUsed: 90, daysAgo: 10);

        $this->artisan('parts:reorder-suggest')->assertSuccessful();

        $line = DraftPurchaseOrderLine::where('part_id', $part->id)->first();
        $this->assertNotNull($line);
        $this->assertSame(DraftPurchaseOrderLine::REASON_LEAD_TIME, $line->trigger_reason);
        $this->assertNotNull($line->projected_stockout_at);
        // qty includes the lead-time buffer: max(1, (2*2 - 5) + ceil(7*1)) = 6
        // (without the buffer it would be max(1, -1) = 1).
        $this->assertSame(6, $line->qty_suggested);
    }

    public function test_part_below_static_threshold_is_tagged_static(): void
    {
        $part = $this->part(qty: 1, threshold: 5);

        $this->artisan('parts:reorder-suggest')->assertSuccessful();

        $line = DraftPurchaseOrderLine::where('part_id', $part->id)->first();
        $this->assertNotNull($line);
        $this->assertSame(DraftPurchaseOrderLine::REASON_STATIC, $line->trigger_reason);
        $this->assertNull($line->projected_stockout_at); // no usage → no forecast
    }

    public function test_part_well_above_effective_threshold_is_not_suggested(): void
    {
        $part = $this->part(qty: 50, threshold: 2);
        $this->consume($part, qtyUsed: 90, daysAgo: 10); // rate 1/day, effective ≈ 9

        $this->artisan('parts:reorder-suggest')->assertSuccessful();

        $this->assertNull(DraftPurchaseOrderLine::where('part_id', $part->id)->first());
    }

    public function test_reorder_is_idempotent_on_rerun(): void
    {
        $part = $this->part(qty: 1, threshold: 5);

        $this->artisan('parts:reorder-suggest')->assertSuccessful();
        $this->artisan('parts:reorder-suggest')->assertSuccessful();

        $this->assertSame(1, DraftPurchaseOrderLine::where('part_id', $part->id)->count());
    }
}
