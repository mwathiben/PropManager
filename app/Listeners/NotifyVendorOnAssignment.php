<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketAssignedToVendor;
use App\Mail\VendorAssignmentMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-54 VENDOR-NOTIFICATIONS-1: emails the vendor when assigned to a
 * ticket. Phase-16 RESIL backoff [30s, 1min, 5min, 30min] with 4 retries
 * — final attempt dead-letters to failed_jobs.
 *
 * Vendor is standalone (no User row, no HasLocalePreference); the
 * Mailable resolves locale from ticket.landlord->preferred_locale.
 *
 * When vendor.email is null (some vendors are phone-only contacts) the
 * listener Log::info's and returns — no exception, no retry storm.
 */
class NotifyVendorOnAssignment implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function handle(TicketAssignedToVendor $event): void
    {
        $vendor = $event->vendor;
        $email = $vendor->email;

        if (! $email) {
            Log::info('NotifyVendorOnAssignment skipped — vendor.email is null', [
                'vendor_id' => $vendor->id,
                'ticket_id' => $event->ticket->id,
            ]);

            return;
        }

        Mail::to($email)->queue(
            new VendorAssignmentMailable($event->ticket, $vendor, $event->note),
        );
    }
}
