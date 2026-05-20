<?php

declare(strict_types=1);

namespace Tests\Feature\VendorPortal;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-70 JOB-ACTIONS: a vendor logs time and marks resolved on a job
 * they accepted. Ownership + accepted state gate every action.
 */
class Phase70JobActionsTest extends TestCase
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

    private function ticketFor(Vendor $vendor, string $vendorStatus = 'accepted', TicketStatus $status = TicketStatus::InProgress): Ticket
    {
        return Ticket::factory()->create([
            'landlord_id' => $this->landlord->id,
            'reporter_id' => $this->landlord->id,
            'vendor_id' => $vendor->id,
            'vendor_status' => $vendorStatus,
            'status' => $status,
        ]);
    }

    public function test_show_renders_for_owner(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->get("/v/portal/tickets/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('VendorPortal/Job')->where('ticket.id', $ticket->id));
    }

    public function test_show_excludes_a_prior_vendors_time_logs(): void
    {
        $current = $this->vendor();
        $prior = $this->vendor();
        $ticket = $this->ticketFor($current);

        // A prior vendor's log lingers on the (reassigned) ticket.
        \App\Models\TicketTimeLog::create([
            'ticket_id' => $ticket->id,
            'vendor_id' => $prior->id,
            'minutes' => 120,
            'note' => 'prior vendor work',
            'logged_at' => now(),
        ]);

        $this->withSession(['vendor_portal_id' => $current->id])
            ->get("/v/portal/tickets/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('time_logs', [])
                ->where('total_minutes', 0));
    }

    public function test_vendor_logs_time_on_accepted_job(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/time", ['minutes' => 90, 'note' => 'Replaced valve'])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_time_logs', [
            'ticket_id' => $ticket->id,
            'vendor_id' => $vendor->id,
            'minutes' => 90,
        ]);
    }

    public function test_cannot_log_time_on_unaccepted_job(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor, vendorStatus: 'pending');

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/time", ['minutes' => 30])
            ->assertStatus(422);

        $this->assertDatabaseCount('ticket_time_logs', 0);
    }

    public function test_cannot_log_time_on_another_vendors_job(): void
    {
        $mine = $this->vendor();
        $theirs = $this->vendor();
        $theirTicket = $this->ticketFor($theirs);

        $this->withSession(['vendor_portal_id' => $mine->id])
            ->post("/v/portal/tickets/{$theirTicket->id}/time", ['minutes' => 30])
            ->assertForbidden();
    }

    public function test_minutes_are_validated(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/time", ['minutes' => 5000])
            ->assertSessionHasErrors('minutes');
    }

    public function test_vendor_marks_job_resolved(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/resolve", ['notes' => 'Fixed and tested'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertSame('Fixed and tested', $ticket->resolution_notes);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'vendor_resolved',
            'old_value' => (string) $vendor->id,
        ]);
    }

    public function test_cannot_resolve_unaccepted_job(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticketFor($vendor, vendorStatus: 'pending');

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->post("/v/portal/tickets/{$ticket->id}/resolve", ['notes' => 'x'])
            ->assertStatus(422);

        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);
    }
}
