<?php

namespace App\Mail;

use App\Enums\Currency;
use App\Models\CreditNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CreditNoteIssued extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public CreditNote $creditNote,
        public ?string $pdfPath = null
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Credit Note Issued - '.$this->creditNote->credit_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.credit-note-issued',
            with: [
                'creditNote' => $this->creditNote,
                'tenant' => $this->creditNote->tenant,
                'unit' => $this->creditNote->lease?->unit,
                'building' => $this->creditNote->lease?->unit?->building,
                'invoice' => $this->creditNote->invoice,
                'currency_symbol' => ($this->creditNote->invoice?->currency ?? $this->creditNote->lease?->unit?->building?->getEffectiveCurrency() ?? Currency::default())->symbol(),
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->pdfPath || ! Storage::disk('private')->exists($this->pdfPath)) {
            return [];
        }

        return [
            Attachment::fromStorage($this->pdfPath, 'private')
                ->as('CreditNote-'.$this->creditNote->credit_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
