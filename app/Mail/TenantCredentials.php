<?php

namespace App\Mail;

use App\Models\Lease;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantCredentials extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $tenant,
        public Lease $lease,
        public string $temporaryPassword,
        public User $landlord
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        $propertyName = $this->lease->unit->building->property->name ?? 'Your New Home';

        return new Envelope(
            subject: "Welcome to {$propertyName} - Your Account Details",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tenant-credentials',
            with: [
                'tenant' => $this->tenant,
                'lease' => $this->lease,
                'temporaryPassword' => $this->temporaryPassword,
                'landlord' => $this->landlord,
                'propertyName' => $this->lease->unit->building->property->name ?? 'Property',
                'buildingName' => $this->lease->unit->building->name ?? '',
                'unitNumber' => $this->lease->unit->unit_number,
                'loginUrl' => route('login'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
