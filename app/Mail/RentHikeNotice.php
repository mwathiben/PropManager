<?php

namespace App\Mail;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentHikeNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Lease $lease,
        public float $oldAmount,
        public float $newAmount,
        public string $effectiveDate,
        public ?string $reason = null
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rent Adjustment Notice - Effective '.$this->effectiveDate,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->lease->load(['tenant', 'unit.building.property']);

        $tenant = $this->lease->tenant;
        $unit = $this->lease->unit;
        $building = $unit->building;
        $property = $building->property;

        return new Content(
            markdown: 'emails.rent-hike-notice',
            with: [
                'tenant' => $tenant,
                'propertyName' => $property->name,
                'buildingName' => $building->name,
                'unitNumber' => $unit->unit_number,
                'oldAmount' => number_format($this->oldAmount, 2),
                'newAmount' => number_format($this->newAmount, 2),
                'effectiveDate' => $this->effectiveDate,
                'reason' => $this->reason,
                'dashboardUrl' => route('dashboard'),
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
