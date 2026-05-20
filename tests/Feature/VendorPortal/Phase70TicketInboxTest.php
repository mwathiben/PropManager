<?php

declare(strict_types=1);

namespace Tests\Feature\VendorPortal;

use App\Enums\TicketStatus;
use App\Events\VendorDeclinedAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Maintenance\VendorAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase-70 TICKET-INBOX: the vendor sees only their own assigned tickets
 * and can accept/decline — the portal's #1 IDOR surface. The session
 * vendor is authoritative; a guessed ticket id from another vendor is
 * rejected.
 */
class Phase70TicketInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    private function vendor(): Vendor
    {
        return Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Acme',
            'email' => 'acme@c.test',
            'is_active' => true,
        ]);
    }

    private function ticketFor(Vendor $vendor, string $vendorStatus = 'pending', TicketStatus $status = TicketStatus::Open): Ticket
    {
        return Ticket::factory()->create([
            'landlord_id' => $this->landlord->id,
            'reporter_id' => $this->landlord->id,
            'vendor_id' => $vendor->id,
            'vendor_status' => $vendorStatus,
            'status' => $status,
        ]);
    }

    public function test_vendor_sees_only_their_tickets(): void
    {
        $mine = $this->vendor();
        $theirs = $this->vendor();
        $myTicket = $this->ticketFor($mine);
        $this->ticketFor($theirs);

        $this->withSession(['vendor_portal_id' => $mine->id])
            ->get('/v/portal/jobs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('VendorPortal/Inbox')
                ->has('tickets', 1)
                ->where('tickets.0.id', $myTicket->id));
    }

    public function test_accept_transitions_and_acknowledges(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/accept")
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('accepted', $ticket->vendor_status);
        $this->assertNotNull($ticket->vendor_responded_at);
        $this->assertSame(TicketStatus::Acknowledged, $ticket->status);
    }

    public function test_decline_clears_vendor_and_fires_event(): void
    {
        Event::fake([VendorDeclinedAssignment::class]);
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/decline", ['reason' => 'Out of my service area'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('declined', $ticket->vendor_status);
        $this->assertNull($ticket->vendor_id);
        Event::assertDispatched(VendorDeclinedAssignment::class);
    }

    public function test_cross_vendor_cannot_accept_anothers_ticket(): void
    {
        $mine = $this->vendor();
        $theirs = $this->vendor();
        $theirTicket = $this->ticketFor($theirs);

        $this->withSession(['vendor_portal_id' => $mine->id])
            ->post("/v/portal/tickets/{$theirTicket->id}/accept")
            ->assertForbidden();

        $this->assertSame('pending', $theirTicket->fresh()->vendor_status);
    }

    public function test_cannot_respond_twice(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor, vendorStatus: 'accepted');

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/accept")
            ->assertStatus(422);
    }

    public function test_cannot_respond_to_a_closed_ticket(): void
    {
        $vendor = $this->vendor();
        // pending vendor_status but the landlord already closed the ticket.
        $ticket = $this->ticketFor($vendor, vendorStatus: 'pending', status: TicketStatus::Closed);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/accept")
            ->assertStatus(422);

        $this->assertSame('pending', $ticket->fresh()->vendor_status);
    }

    public function test_assignment_sets_pending_status(): void
    {
        $vendor = $this->vendor();
        $ticket = Ticket::factory()->create([
            'landlord_id' => $this->landlord->id,
            'reporter_id' => $this->landlord->id,
        ]);

        $this->actingAs($this->landlord);
        app(VendorAssignmentService::class)->assign($ticket, $vendor);

        $ticket->refresh();
        $this->assertSame($vendor->id, $ticket->vendor_id);
        $this->assertSame('pending', $ticket->vendor_status);
    }
}
