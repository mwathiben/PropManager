<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Enums\TicketStatus;
use App\Events\VendorDeclinedAssignment;
use App\Models\Ticket;
use App\Models\TicketActivity;
use Illuminate\Support\Facades\DB;

/**
 * Phase-70 TICKET-INBOX-1: the vendor's response to an assignment.
 * Accept acknowledges the ticket; decline returns it to the landlord
 * pool (clears vendor_id) and fires an event the landlord is notified on.
 * Caller (the portal controller) has already verified the ticket belongs
 * to the responding vendor.
 */
class VendorAssignmentResponseService
{
    public function accept(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket) {
            // Re-load under a row lock + re-assert pending so concurrent
            // double-clicks/retries can't both transition the ticket.
            $ticket = $this->lockPending($ticket);

            $ticket->update([
                'vendor_status' => 'accepted',
                'vendor_responded_at' => now(),
            ]);

            if ($ticket->status === TicketStatus::Open) {
                $ticket->acknowledge();
            }

            $this->log($ticket, 'vendor_accepted', null);

            return $ticket->fresh();
        });
    }

    public function decline(Ticket $ticket, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $reason) {
            $ticket = $this->lockPending($ticket);
            $vendor = $ticket->vendor;

            // Record the decline (capturing the declining vendor id before it
            // is cleared), then return the ticket to the landlord pool.
            $ticket->update([
                'vendor_status' => 'declined',
                'vendor_responded_at' => now(),
                'vendor_id' => null,
            ]);

            $this->log($ticket, 'vendor_declined', $reason, $vendor !== null ? (string) $vendor->id : null);

            if ($vendor !== null) {
                VendorDeclinedAssignment::dispatch($ticket->fresh(), $vendor, $reason);
            }

            return $ticket->fresh();
        });
    }

    /**
     * Lock the ticket row and confirm it is still awaiting a vendor response
     * (the race guard — the loser of a concurrent response gets 422).
     */
    private function lockPending(Ticket $ticket): Ticket
    {
        $locked = Ticket::withoutGlobalScopes()->lockForUpdate()->findOrFail($ticket->id);
        abort_unless($locked->vendor_status === 'pending', 422, __('vendor_portal.inbox.already_responded'));

        return $locked;
    }

    private function log(Ticket $ticket, string $action, ?string $description, ?string $oldValue = null): void
    {
        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'landlord_id' => $ticket->landlord_id,
            'user_id' => null,
            'action' => $action,
            'old_value' => $oldValue,
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
