<?php

namespace App\Mail;

use App\Enums\Currency;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class InvoiceSent extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Invoice $invoice
    ) {
        $this->afterCommit = true;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.subjects.invoice_sent', ['number' => $this->invoice->invoice_number]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Phase-98: an invoice is anchored to a lease (tenant) OR a water connection
        // (water client). Resolve the recipient + property context from whichever.
        if ($this->invoice->isWaterClientInvoice()) {
            $this->invoice->load(['waterConnection.client', 'waterConnection.unit.building.property']);
            $connection = $this->invoice->waterConnection;
            $recipient = $connection?->client;
            $unit = $connection?->unit;
            $contextLabel = $connection?->identifier;
            $invoiceUrl = route('water-client.finances');
        } else {
            $this->invoice->load(['lease.tenant', 'lease.unit.building.property']);
            $recipient = $this->invoice->lease?->tenant;
            $unit = $this->invoice->lease?->unit;
            $contextLabel = $unit?->unit_number;
            $invoiceUrl = route('invoices.show', $this->invoice);
        }
        $building = $unit?->building;
        $property = $building?->property;

        return new Content(
            markdown: 'emails.invoice-sent',
            with: [
                'invoice' => $this->invoice,
                'tenant' => $recipient,
                'invoiceNumber' => $this->invoice->invoice_number,
                'billingPeriod' => $this->invoice->billing_period->translatedFormat('F Y'),
                'rentDue' => number_format($this->invoice->rent_due, 2),
                'waterDue' => number_format($this->invoice->water_due ?? 0, 2),
                'arrears' => number_format($this->invoice->arrears ?? 0, 2),
                'totalDue' => number_format($this->invoice->total_due, 2),
                'dueDate' => $this->invoice->due_date->translatedFormat('F d, Y'),
                'propertyName' => $property?->name ?? '',
                'buildingName' => $building?->name ?? '',
                'unitNumber' => $contextLabel ?? '',
                'invoiceUrl' => $invoiceUrl,
                'currency_symbol' => ($this->invoice->currency ?? Currency::default())->symbol(),
                'unsubscribeUrl' => $recipient
                    ? URL::temporarySignedRoute('email.preferences', now()->addDays(30), ['user' => $recipient->id])
                    : URL::to('/'),
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
