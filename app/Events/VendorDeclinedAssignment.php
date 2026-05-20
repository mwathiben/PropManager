<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use App\Models\Vendor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-70 TICKET-INBOX-1: fired when a vendor declines a ticket
 * assignment via the portal. The ticket's vendor_id is already cleared
 * (back to the landlord pool); NotifyLandlordOnVendorDecline emails the
 * owning landlord so they can reassign.
 */
class VendorDeclinedAssignment
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly Vendor $vendor,
        public readonly ?string $reason = null,
    ) {}
}
