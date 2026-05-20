<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\VendorDeclinedAssignment;
use App\Mail\VendorDeclinedMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-70 TICKET-INBOX-1: emails the owning landlord when a vendor
 * declines a ticket, so they can reassign. Mirrors the Phase-54
 * NotifyVendorOnAssignment resilience (retries + backoff). Fail-soft
 * when the landlord has no email.
 */
class NotifyLandlordOnVendorDecline implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function handle(VendorDeclinedAssignment $event): void
    {
        $landlord = $event->ticket->landlord;

        if ($landlord === null || ! $landlord->email) {
            Log::info('NotifyLandlordOnVendorDecline skipped — no landlord email', [
                'ticket_id' => $event->ticket->id,
            ]);

            return;
        }

        Mail::to($landlord->email)->queue(
            new VendorDeclinedMailable($event->ticket, $event->vendor, $event->reason),
        );
    }
}
