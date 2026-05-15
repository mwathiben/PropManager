<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public TenantInvitation $invitation
    ) {
        $this->afterCommit = true;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $propertyName = $this->invitation->unit->building->property->name;

        $key = $this->invitation->isForExistingUser()
            ? 'emails.subjects.tenant_invitation_existing'
            : 'emails.subjects.tenant_invitation_new';

        return new Envelope(
            subject: __($key, ['property' => $propertyName]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tenant-invitation',
            with: [
                'invitation' => $this->invitation,
                'landlordName' => $this->invitation->landlord->name,
                'propertyName' => $this->invitation->unit->building->property->name,
                'buildingName' => $this->invitation->unit->building->name,
                'unitNumber' => $this->invitation->unit->unit_number,
                'rentAmount' => number_format($this->invitation->rent_amount, 2),
                'depositAmount' => number_format($this->invitation->deposit_amount, 2),
                'startDate' => $this->invitation->start_date->translatedFormat('F d, Y'),
                'acceptUrl' => route('tenant-invitations.show', $this->invitation->token),
                'expiresAt' => $this->invitation->expires_at->translatedFormat('F d, Y'),
                'isExistingUser' => $this->invitation->isForExistingUser(),
                'currency_symbol' => $this->invitation->unit->building->getEffectiveCurrency()->symbol(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
