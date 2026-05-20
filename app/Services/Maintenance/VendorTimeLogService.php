<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Ticket;
use App\Models\TicketTimeLog;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Phase-70 JOB-ACTIONS-1: a vendor logs labour time against a ticket they
 * have accepted. Guards ownership + accepted state as defence-in-depth
 * behind the portal controller's session check.
 */
class VendorTimeLogService
{
    public function log(Ticket $ticket, Vendor $vendor, int $minutes, ?string $note = null): TicketTimeLog
    {
        return DB::transaction(function () use ($ticket, $vendor, $minutes, $note) {
            // Re-assert under a row lock so a reassign/decline between the
            // controller check and the write can't let time land on a ticket
            // that is no longer this vendor's accepted job.
            $locked = Ticket::withoutGlobalScopes()->lockForUpdate()->findOrFail($ticket->id);
            abort_unless($locked->vendor_id === $vendor->id, 403);
            abort_unless($locked->vendor_status === 'accepted', 422, __('vendor_portal.job.not_accepted'));

            return TicketTimeLog::create([
                'ticket_id' => $locked->id,
                'vendor_id' => $vendor->id,
                'minutes' => $minutes,
                'note' => $note,
                'logged_at' => now(),
            ]);
        });
    }
}
