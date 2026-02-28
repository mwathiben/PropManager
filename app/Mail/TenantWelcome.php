<?php

namespace App\Mail;

use App\Models\Lease;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TenantWelcome extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $tenant,
        public TenantInvitation $invitation,
        public Lease $lease
    ) {
        $this->afterCommit = true;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $propertyName = $this->invitation->unit->building->property->name;

        return new Envelope(
            subject: "Welcome to {$propertyName} - Your Lease is Active",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tenant-welcome',
            with: [
                'tenant' => $this->tenant,
                'lease' => $this->lease,
                'landlordName' => $this->invitation->landlord->name,
                'landlordEmail' => $this->invitation->landlord->email,
                'landlordPhone' => $this->invitation->landlord->mobile_number,
                'propertyName' => $this->invitation->unit->building->property->name,
                'buildingName' => $this->invitation->unit->building->name,
                'unitNumber' => $this->invitation->unit->unit_number,
                'rentAmount' => number_format($this->lease->rent_amount, 2),
                'depositAmount' => number_format($this->lease->deposit_amount, 2),
                'startDate' => $this->lease->start_date->format('F d, Y'),
                'dashboardUrl' => route('dashboard'),
                'currency_symbol' => $this->invitation->unit->building->getEffectiveCurrency()->symbol(),
                'unsubscribeUrl' => URL::temporarySignedRoute(
                    'email.preferences',
                    now()->addDays(30),
                    ['user' => $this->tenant->id]
                ),
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
