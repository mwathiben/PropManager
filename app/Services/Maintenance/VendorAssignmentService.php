<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Events\TicketAssignedToVendor;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase-49 VENDOR-MARKETPLACE-2: canonical write path for assigning an
 * external Vendor (contractor) to a Ticket. Writes vendor_id under
 * DB::transaction + logs a TicketActivity row + fires
 * TicketAssignedToVendor.
 *
 * Vendor must belong to the same landlord as the ticket — guards
 * against cross-tenant data leaks.
 */
class VendorAssignmentService
{
    public function assign(Ticket $ticket, Vendor $vendor, ?string $note = null): Ticket
    {
        if ($vendor->landlord_id !== $ticket->landlord_id) {
            throw new InvalidArgumentException(
                "Vendor {$vendor->id} (landlord {$vendor->landlord_id}) cannot be assigned to ticket {$ticket->id} (landlord {$ticket->landlord_id})."
            );
        }

        return DB::transaction(function () use ($ticket, $vendor, $note) {
            $previousVendorId = $ticket->vendor_id;
            $ticket->update(['vendor_id' => $vendor->id]);

            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'landlord_id' => $ticket->landlord_id,
                'user_id' => Auth::id(),
                'action' => 'vendor_assigned',
                'old_value' => $previousVendorId !== null ? (string) $previousVendorId : null,
                'new_value' => (string) $vendor->id,
                'description' => $note,
            ]);

            TicketAssignedToVendor::dispatch($ticket, $vendor, $note);

            return $ticket->fresh();
        });
    }
}
