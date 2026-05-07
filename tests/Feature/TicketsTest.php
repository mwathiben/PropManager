<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketsTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected User $caretaker;

    protected User $tenant;

    protected Property $property;

    protected Building $building;

    protected Unit $unit;

    protected Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        // Create landlord
        $this->landlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_id' => null,
        ]);

        // Authenticate as landlord for setup
        $this->actingAs($this->landlord);

        // Create property
        $this->property = Property::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Property',
            'type' => 'residential',
            'address' => '123 Test Street',
        ]);

        // Create caretaker
        $this->caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        // Create building with caretaker
        $this->building = Building::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'caretaker_id' => $this->caretaker->id,
            'name' => 'Building A',
            'total_floors' => 2,
            'units_per_floor' => 2,
        ]);

        // Create unit
        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'landlord_id' => $this->landlord->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 15000,
        ]);

        // Create tenant
        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        // Create lease
        $this->lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(9),
            'rent_amount' => 15000,
            'deposit_amount' => 15000,
            'is_active' => true,
        ]);
    }

    // ==================== INDEX TESTS ====================

    public function test_tickets_index_page_can_be_rendered_for_landlord(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/tickets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Tickets/Index'));
    }

    public function test_tickets_index_page_can_be_rendered_for_caretaker(): void
    {
        $response = $this->actingAs($this->caretaker)
            ->get('/tickets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Tickets/Index'));
    }

    public function test_tickets_index_page_can_be_rendered_for_tenant(): void
    {
        $response = $this->actingAs($this->tenant)
            ->get('/tickets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Tickets/Index'));
    }

    public function test_landlord_sees_all_tickets(): void
    {
        // Create tickets from different reporters
        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'unit_id' => $this->unit->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Leaking Pipe',
            'description' => 'There is a leak in the kitchen.',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/tickets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
        );
    }

    public function test_caretaker_sees_only_assigned_tickets(): void
    {
        // Create ticket assigned to caretaker
        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'electrical',
            'title' => 'Broken Light',
            'description' => 'Light fixture not working.',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        // Create ticket assigned to someone else (via building without caretaker set for this test)
        $otherCaretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->landlord->id,
            'assigned_to' => $otherCaretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Other Issue',
            'description' => 'Assigned to other caretaker.',
            'priority' => 'low',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->caretaker)
            ->get('/tickets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.title', 'Broken Light')
        );
    }

    public function test_tenant_sees_only_own_tickets(): void
    {
        // Create ticket by tenant
        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'unit_id' => $this->unit->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'My Issue',
            'description' => 'My description.',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        // Create ticket by landlord
        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->landlord->id,
            'category' => 'complaint',
            'subcategory' => 'noise',
            'title' => 'Landlord Report',
            'description' => 'Landlord description.',
            'priority' => 'low',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->tenant)
            ->get('/tickets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.title', 'My Issue')
        );
    }

    public function test_tickets_can_be_filtered_by_status(): void
    {
        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Open Ticket',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'electrical',
            'title' => 'Resolved Ticket',
            'description' => 'Description.',
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/tickets?status=open');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.title', 'Open Ticket')
        );
    }

    public function test_tickets_can_be_filtered_by_category(): void
    {
        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Issue Ticket',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'complaint',
            'subcategory' => 'noise',
            'title' => 'Complaint Ticket',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/tickets?category=complaint');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.title', 'Complaint Ticket')
        );
    }

    // ==================== CREATE TESTS ====================

    public function test_ticket_create_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->tenant)
            ->get('/tickets/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/Create')
            ->has('buildings')
            ->has('subcategories')
            ->has('priorities')
        );
    }

    public function test_tenant_can_create_issue_ticket(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->tenant)
            ->post('/tickets', [
                'building_id' => $this->building->id,
                'unit_id' => $this->unit->id,
                'category' => 'issue',
                'subcategory' => 'plumbing',
                'title' => 'Leaking Kitchen Faucet',
                'description' => 'The faucet has been dripping for 2 days.',
                'location' => 'Kitchen',
                'priority' => 'medium',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'reporter_id' => $this->tenant->id,
            'building_id' => $this->building->id,
            'unit_id' => $this->unit->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Leaking Kitchen Faucet',
            'status' => 'open',
        ]);
    }

    public function test_tenant_can_create_complaint_ticket(): void
    {
        $response = $this->actingAs($this->tenant)
            ->post('/tickets', [
                'building_id' => $this->building->id,
                'category' => 'complaint',
                'subcategory' => 'noise',
                'title' => 'Loud Music at Night',
                'description' => 'Neighbor plays loud music after 10pm.',
                'priority' => 'high',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'category' => 'complaint',
            'subcategory' => 'noise',
            'title' => 'Loud Music at Night',
        ]);
    }

    public function test_ticket_auto_assigns_to_building_caretaker(): void
    {
        $response = $this->actingAs($this->tenant)
            ->post('/tickets', [
                'building_id' => $this->building->id,
                'category' => 'issue',
                'subcategory' => 'electrical',
                'title' => 'Power Outlet Not Working',
                'description' => 'Outlet in bedroom has no power.',
                'priority' => 'medium',
            ]);

        $ticket = Ticket::where('title', 'Power Outlet Not Working')->first();

        $this->assertNotNull($ticket);
        $this->assertEquals($this->caretaker->id, $ticket->assigned_to);
    }

    public function test_ticket_creation_logs_activity(): void
    {
        $this->actingAs($this->tenant)
            ->post('/tickets', [
                'building_id' => $this->building->id,
                'category' => 'issue',
                'subcategory' => 'plumbing',
                'title' => 'Test Activity Logging',
                'description' => 'Testing activity log.',
                'priority' => 'low',
            ]);

        $ticket = Ticket::where('title', 'Test Activity Logging')->first();

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'created',
            'user_id' => $this->tenant->id,
        ]);
    }

    public function test_ticket_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->tenant)
            ->post('/tickets', [
                // Missing required fields
            ]);

        $response->assertSessionHasErrors(['building_id', 'category', 'subcategory', 'title', 'description']);
    }

    // ==================== SHOW TESTS ====================

    public function test_ticket_show_page_can_be_rendered(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Test Ticket',
            'description' => 'Test description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get("/tickets/{$ticket->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/Show')
            ->has('ticket')
            ->has('ticket.activities')
            ->has('ticket.comments')
        );
    }

    public function test_reporter_can_view_own_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'My Ticket',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->tenant)
            ->get("/tickets/{$ticket->id}");

        $response->assertStatus(200);
    }

    public function test_assignee_can_view_assigned_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Assigned Ticket',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->caretaker)
            ->get("/tickets/{$ticket->id}");

        $response->assertStatus(200);
    }

    // ==================== STATUS UPDATE TESTS ====================

    public function test_caretaker_can_acknowledge_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'To Acknowledge',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->caretaker)
            ->put("/tickets/{$ticket->id}", [
                'status' => 'acknowledged',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals(\App\Enums\TicketStatus::Acknowledged, $ticket->status);
    }

    public function test_caretaker_can_start_work_on_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'To Start Work',
            'description' => 'Description.',
            'status' => 'acknowledged',
        ]);

        $response = $this->actingAs($this->caretaker)
            ->put("/tickets/{$ticket->id}", [
                'status' => 'in_progress',
            ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertEquals(\App\Enums\TicketStatus::InProgress, $ticket->status);
    }

    public function test_caretaker_can_resolve_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'To Resolve',
            'description' => 'Description.',
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->caretaker)
            ->post("/tickets/{$ticket->id}/resolve", [
                'resolution_notes' => 'Fixed the leaking pipe.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals(\App\Enums\TicketStatus::Resolved, $ticket->status);
        $this->assertEquals('Fixed the leaking pipe.', $ticket->resolution_notes);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_landlord_can_close_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'To Close',
            'description' => 'Description.',
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($this->landlord)
            ->post("/tickets/{$ticket->id}/close");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals(\App\Enums\TicketStatus::Closed, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_status_change_logs_activity(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Activity Test',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $this->actingAs($this->caretaker)
            ->put("/tickets/{$ticket->id}", [
                'status' => 'acknowledged',
            ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'Acknowledged',
        ]);
    }

    // ==================== ASSIGNMENT TESTS ====================

    public function test_landlord_can_reassign_ticket(): void
    {
        $newCaretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'To Reassign',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->landlord)
            ->post("/tickets/{$ticket->id}/assign", [
                'assigned_to' => $newCaretaker->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals($newCaretaker->id, $ticket->assigned_to);
    }

    public function test_caretaker_cannot_reassign_ticket(): void
    {
        $newCaretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'No Reassign',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->caretaker)
            ->post("/tickets/{$ticket->id}/assign", [
                'assigned_to' => $newCaretaker->id,
            ]);

        $response->assertStatus(403);
    }

    // ==================== COMMENT TESTS ====================

    public function test_user_can_add_comment_to_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Comment Test',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->tenant)
            ->post("/tickets/{$ticket->id}/comment", [
                'comment' => 'This is a test comment.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->tenant->id,
            'comment' => 'This is a test comment.',
            'is_internal' => false,
        ]);
    }

    public function test_caretaker_can_add_internal_comment(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'assigned_to' => $this->caretaker->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Internal Comment Test',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->caretaker)
            ->post("/tickets/{$ticket->id}/comment", [
                'comment' => 'Internal note for landlord.',
                'is_internal' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'is_internal' => true,
        ]);
    }

    public function test_comment_adds_activity_log(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Activity Log Test',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $this->actingAs($this->tenant)
            ->post("/tickets/{$ticket->id}/comment", [
                'comment' => 'Another comment.',
            ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'commented',
            'user_id' => $this->tenant->id,
        ]);
    }

    // ==================== FEEDBACK TESTS ====================

    public function test_tenant_can_submit_feedback_on_closed_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Feedback Test',
            'description' => 'Description.',
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->tenant)
            ->post("/tickets/{$ticket->id}/feedback", [
                'rating' => 5,
                'comments' => 'Great service!',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_feedback', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->tenant->id,
            'rating' => 5,
            'comments' => 'Great service!',
        ]);
    }

    public function test_feedback_validates_rating(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Validation Test',
            'description' => 'Description.',
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->tenant)
            ->post("/tickets/{$ticket->id}/feedback", [
                'rating' => 6, // Invalid - must be 1-5
                'comments' => 'Test.',
            ]);

        $response->assertSessionHasErrors(['rating']);
    }

    public function test_feedback_cannot_be_submitted_twice(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Duplicate Feedback Test',
            'description' => 'Description.',
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        // First feedback
        TicketFeedback::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->tenant->id,
            'rating' => 4,
        ]);

        // Try second feedback
        $response = $this->actingAs($this->tenant)
            ->post("/tickets/{$ticket->id}/feedback", [
                'rating' => 5,
            ]);

        $response->assertSessionHas('error');
    }

    // ==================== DELETE TESTS ====================

    public function test_reporter_can_cancel_open_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'To Cancel',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->tenant)
            ->delete("/tickets/{$ticket->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals(\App\Enums\TicketStatus::Cancelled, $ticket->status);
    }

    public function test_reporter_cannot_cancel_in_progress_ticket(): void
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'In Progress',
            'description' => 'Description.',
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->tenant)
            ->delete("/tickets/{$ticket->id}");

        $response->assertSessionHas('error');

        $ticket->refresh();
        $this->assertEquals(\App\Enums\TicketStatus::InProgress, $ticket->status);
    }

    // ==================== MULTI-TENANCY TESTS ====================

    public function test_landlord_cannot_see_other_landlord_tickets(): void
    {
        $otherLandlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_id' => null,
        ]);

        // Create ticket as other landlord
        $this->actingAs($otherLandlord);

        $otherProperty = Property::create([
            'landlord_id' => $otherLandlord->id,
            'name' => 'Other Property',
            'type' => 'residential',
            'address' => '456 Other Street',
        ]);

        $otherBuilding = Building::create([
            'property_id' => $otherProperty->id,
            'landlord_id' => $otherLandlord->id,
            'name' => 'Other Building',
            'total_floors' => 1,
            'units_per_floor' => 1,
        ]);

        $otherTicket = Ticket::create([
            'landlord_id' => $otherLandlord->id,
            'building_id' => $otherBuilding->id,
            'reporter_id' => $otherLandlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Other Landlord Ticket',
            'description' => 'Description.',
            'status' => 'open',
        ]);

        // Try to access as first landlord
        $response = $this->actingAs($this->landlord)
            ->get("/tickets/{$otherTicket->id}");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_tickets(): void
    {
        auth()->logout();

        $response = $this->get('/tickets');

        $response->assertRedirect('/login');
    }
}
