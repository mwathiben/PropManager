<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use App\Models\Vendor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-49 VENDOR-MARKETPLACE-2: fired when VendorAssignmentService
 * assigns a vendor to a ticket. A future Notify listener can email the
 * vendor (when vendor.email is set) — Phase 49 just establishes the
 * event signature.
 */
class TicketAssignedToVendor
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly Vendor $vendor,
        public readonly ?string $note = null,
    ) {}
}
