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
 * Phase-54 VENDOR-NOTIFICATIONS-1/3: sends a vendor an email when
 * assigned to a ticket. ShouldQueue + afterCommit mirrors the Phase-47
 * OnboardingResumeMailable pattern.
 *
 * Locale resolution (vendors are not Users so HasLocalePreference does
 * NOT auto-fire): falls back to ticket.landlord->preferred_locale,
 * then config('app.fallback_locale'). The Mailable wraps the view
 * render in App::setLocale() to ensure __() calls inside the blade
 * file see the resolved locale.
 */
class VendorAssignmentMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Vendor $vendor,
        public ?string $note = null,
    ) {
        $this->afterCommit = true;

        $this->locale($this->resolveLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('maintenance.vendor_assigned.subject', [
                'ticket' => $this->ticket->title,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.maintenance.vendor-assigned',
            text: 'emails.maintenance.vendor-assigned-text',
            with: [
                'ticket' => $this->ticket,
                'vendor' => $this->vendor,
                'note' => $this->note,
            ],
        );
    }

    private function resolveLocale(): string
    {
        $landlord = $this->ticket->landlord;
        if ($landlord && method_exists($landlord, 'preferredLocale')) {
            $locale = $landlord->preferredLocale();
            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        return (string) config('app.fallback_locale', 'en');
    }
}
