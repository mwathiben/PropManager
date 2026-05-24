<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase-102 OWNER-PORTAL: emails a property owner a deep-link to create their portal
 * login (mirrors WaterClientInvitation).
 */
class OwnerInvitation extends Mailable implements ShouldQueue
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
            subject: __('emails.subjects.owner_invitation'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.owner-invitation',
            with: [
                'landlordName' => $this->invitation->landlord->name,
                'ownerName' => $this->invitation->propertyOwner?->name,
                'acceptUrl' => route('owner-invite.show', $this->invitation->token),
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

    /**
     * A silently-dropped invite leaves the owner with no link AND the landlord with a
     * 30-day "already pending" block on resending — log it so the failure is debuggable
     * rather than invisible.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Owner invitation email failed', [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'error' => $e->getMessage(),
        ]);
    }
}
