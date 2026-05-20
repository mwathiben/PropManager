<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Phase-70 JOB-ACTIONS-2: a vendor marks a job they accepted as resolved.
 * Sets the ticket Resolved (+ resolution notes) and records the resolving
 * vendor in the activity log so the landlord sees who/when before closing.
 */
class VendorJobResolutionService
{
    public function resolve(Ticket $ticket, Vendor $vendor, ?string $notes = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $vendor, $notes) {
            // Re-assert under a row lock so a concurrent resolve, or a
            // reassign/decline that landed between the controller check and
            // here, can't be clobbered.
            $locked = Ticket::withoutGlobalScopes()->lockForUpdate()->findOrFail($ticket->id);
            abort_unless($locked->vendor_id === $vendor->id, 403);
            abort_unless($locked->vendor_status === 'accepted', 422, __('vendor_portal.job.not_accepted'));
            abort_unless($locked->isOpen(), 422, __('vendor_portal.job.not_open'));

            $locked->resolve($notes);

            TicketActivity::create([
                'ticket_id' => $locked->id,
                'landlord_id' => $locked->landlord_id,
                'user_id' => null,
                'action' => 'vendor_resolved',
                'old_value' => (string) $vendor->id,
                'description' => $notes,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
