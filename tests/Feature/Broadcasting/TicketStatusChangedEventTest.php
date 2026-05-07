<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Models\Building;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStatusChangedEventTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Building $building;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);

        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->building = Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'total_floors' => 2,
            'units_per_floor' => 4,
            'landlord_id' => $this->landlord->id,
            'building_type' => 'residential_apartment',
        ]);

        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        Ticket::withoutEvents(function () {
            $this->ticket = Ticket::create([
                'landlord_id' => $this->landlord->id,
                'building_id' => $this->building->id,
                'reporter_id' => $this->tenant->id,
                'category' => 'issue',
                'subcategory' => 'plumbing',
                'title' => 'Test Ticket',
                'description' => 'Test description',
                'priority' => 'medium',
                'status' => 'open',
            ]);
        });
    }

    public function test_broadcasts_to_landlord_channel(): void
    {
        $event = new TicketStatusChanged($this->ticket, TicketStatus::Open, TicketStatus::InProgress);
        $channels = collect($event->broadcastOn());

        $this->assertTrue(
            $channels->contains(fn ($ch) => $ch instanceof PrivateChannel
                && $ch->name === 'private-landlord.'.$this->ticket->landlord_id
            ),
            "Event should broadcast to private-landlord.{$this->ticket->landlord_id}"
        );
    }

    public function test_broadcasts_to_tenant_reporter_channel(): void
    {
        $event = new TicketStatusChanged($this->ticket, TicketStatus::Open, TicketStatus::InProgress);
        $channels = collect($event->broadcastOn());

        $this->assertTrue(
            $channels->contains(fn ($ch) => $ch instanceof PrivateChannel
                && $ch->name === 'private-tenant.'.$this->ticket->reporter_id
            ),
            "Event should broadcast to private-tenant.{$this->ticket->reporter_id}"
        );
    }

    public function test_broadcast_payload_contains_status_transition(): void
    {
        $event = new TicketStatusChanged($this->ticket, TicketStatus::Open, TicketStatus::InProgress);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('old_status', $payload);
        $this->assertArrayHasKey('new_status', $payload);
        $this->assertEquals('open', $payload['old_status']);
        $this->assertEquals('in_progress', $payload['new_status']);
    }

    public function test_landlord_open_count_calculated(): void
    {
        Ticket::withoutEvents(function () {
            Ticket::create([
                'landlord_id' => $this->landlord->id,
                'building_id' => $this->building->id,
                'reporter_id' => $this->tenant->id,
                'category' => 'issue',
                'subcategory' => 'electrical',
                'title' => 'Another Ticket',
                'description' => 'Another issue',
                'priority' => 'low',
                'status' => 'open',
            ]);

            Ticket::create([
                'landlord_id' => $this->landlord->id,
                'building_id' => $this->building->id,
                'reporter_id' => $this->tenant->id,
                'category' => 'issue',
                'subcategory' => 'plumbing',
                'title' => 'Third Ticket',
                'description' => 'Third issue',
                'priority' => 'high',
                'status' => 'acknowledged',
            ]);
        });

        $event = new TicketStatusChanged($this->ticket, TicketStatus::Open, TicketStatus::InProgress);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('landlord_open_count', $payload);
        $this->assertEquals(3, $payload['landlord_open_count']);
    }

    public function test_caretaker_open_count_included_when_assigned(): void
    {
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        Ticket::withoutEvents(function () use ($caretaker) {
            $this->ticket->update(['assigned_to' => $caretaker->id]);

            Ticket::create([
                'landlord_id' => $this->landlord->id,
                'building_id' => $this->building->id,
                'reporter_id' => $this->tenant->id,
                'category' => 'issue',
                'subcategory' => 'electrical',
                'title' => 'Another Ticket',
                'description' => 'Another issue',
                'priority' => 'low',
                'status' => 'open',
                'assigned_to' => $caretaker->id,
            ]);
        });

        $this->ticket->refresh();
        $event = new TicketStatusChanged($this->ticket, TicketStatus::Open, TicketStatus::Acknowledged);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('caretaker_open_count', $payload);
        $this->assertEquals(2, $payload['caretaker_open_count']);
    }

    public function test_caretaker_open_count_null_when_unassigned(): void
    {
        $event = new TicketStatusChanged($this->ticket, TicketStatus::Open, TicketStatus::InProgress);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('caretaker_open_count', $payload);
        $this->assertNull($payload['caretaker_open_count']);
    }
}
