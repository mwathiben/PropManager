<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\TicketStatus;
use App\Models\Building;
use App\Models\CaretakerAssignment;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\Caretaker\CaretakerBuildingSummaryService;
use App\Services\Caretaker\CaretakerFirstTaskResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-77 CARETAKER-CONTEXT: building summary (counts) + first-task resolver,
 * both landlord-scoped.
 */
class Phase77CaretakerContextTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Building $building;

    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
        $this->caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);
    }

    private function assign(Building $building, string $status = CaretakerAssignment::STATUS_ACCEPTED): void
    {
        CaretakerAssignment::create([
            'caretaker_id' => $this->caretaker->id,
            'building_id' => $building->id,
            'status' => $status,
            'assigned_at' => now(),
        ]);
    }

    private function ticket(Building $building, int $daysAgo = 1, string $status = 'open'): Ticket
    {
        return Model::withoutEvents(function () use ($building, $daysAgo, $status) {
            $ticket = Ticket::create([
                'landlord_id' => $building->landlord_id,
                'building_id' => $building->id,
                'reporter_id' => $building->landlord_id,
                'category' => 'issue',
                'subcategory' => 'plumbing',
                'title' => 'Leak',
                'description' => 'X',
                'priority' => 'high',
                'status' => $status,
            ]);
            $ticket->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();

            return $ticket;
        });
    }

    public function test_summary_counts_units_occupancy_and_open_tickets(): void
    {
        Unit::where('building_id', $this->building->id)->limit(3)->update(['status' => 'occupied']);
        $this->ticket($this->building);
        $this->ticket($this->building);
        $this->ticket($this->building, status: TicketStatus::Resolved->value);
        $this->assign($this->building);

        $summary = app(CaretakerBuildingSummaryService::class)->forCaretaker($this->caretaker);

        $this->assertCount(1, $summary);
        $this->assertSame(8, $summary[0]['unit_count']);
        $this->assertSame(3, $summary[0]['occupied_count']);
        $this->assertSame(2, $summary[0]['open_ticket_count']);
    }

    public function test_summary_excludes_another_landlords_building(): void
    {
        $otherBuilding = Building::where('landlord_id', Model::withoutEvents(
            fn () => $this->createLandlordWithFullSetup()['landlord']
        )->id)->first();

        $summary = app(CaretakerBuildingSummaryService::class)->forBuildings($this->caretaker, [$otherBuilding->id]);

        $this->assertSame([], $summary);
    }

    public function test_first_task_resolves_oldest_open_ticket_on_an_assigned_building(): void
    {
        $this->assign($this->building);
        $this->ticket($this->building, daysAgo: 2);
        $oldest = $this->ticket($this->building, daysAgo: 20);

        $target = app(CaretakerFirstTaskResolver::class)->resolve($this->caretaker);

        $this->assertSame(route('tickets.show', $oldest->id), $target);
    }

    public function test_first_task_falls_back_to_maintenance_hub_when_no_open_ticket(): void
    {
        $this->assign($this->building);

        $target = app(CaretakerFirstTaskResolver::class)->resolve($this->caretaker);

        $this->assertSame(route('maintenance.hub'), $target);
    }
}
