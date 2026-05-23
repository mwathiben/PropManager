<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-95 WATER-CLIENT-ONBOARDING: emails a non-tenant water client a deep-link
 * to create their account + onboard (mirrors CaretakerInvitation, water-only).
 */
class WaterClientInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.subjects.water_client_invitation'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.water-client-invitation',
            with: [
                'landlordName' => $this->invitation->landlord->name,
                'identifier' => $this->invitation->waterConnection?->identifier,
                'acceptUrl' => route('water-invite.show', $this->invitation->token),
                'expiresAt' => $this->invitation->getExpiresAt()->translatedFormat('F d, Y'),
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
