<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ticket;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-70 TICKET-INBOX-1: tells the landlord a vendor declined a ticket
 * (so they can reassign). Queued + locale-aware from the landlord's own
 * preference (the landlord IS a User here).
 */
class VendorDeclinedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Vendor $vendor,
        public ?string $reason = null,
    ) {
        $this->afterCommit = true;

        $landlord = $ticket->landlord;
        if ($landlord && method_exists($landlord, 'preferredLocale')) {
            $locale = $landlord->preferredLocale();
            if (is_string($locale) && $locale !== '') {
                $this->locale($locale);
            }
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('vendor_portal.declined_email.subject', ['ticket' => $this->ticket->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.vendor.declined',
            with: ['ticket' => $this->ticket, 'vendor' => $this->vendor, 'reason' => $this->reason],
        );
    }
}
