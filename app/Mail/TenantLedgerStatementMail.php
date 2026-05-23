<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Landlord/caretaker-initiated tenant account statement: the markdown body
 * (emails.tenant-statement) plus the rendered ledger PDF as an attachment.
 *
 * This MUST be a Mailable rather than Mail::send('emails.tenant-statement', ...):
 * the view uses <x-mail::message>/<x-mail::panel>, whose `mail` view namespace is
 * registered ONLY by the markdown render pipeline. A plain view send leaves the
 * hint undefined and 500s with "No hint path defined for [mail]".
 */
class TenantLedgerStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public readonly User $tenant,
        public readonly User $landlord,
        public readonly array $summary,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly string $currencySymbol,
        public readonly string $pdfContent,
        public readonly string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->landlord->email
                ? new Address($this->landlord->email, $this->landlord->name ?? 'Property Management')
                : null,
            subject: 'Your Account Statement',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tenant-statement',
            with: [
                'tenant' => $this->tenant,
                'landlord' => $this->landlord,
                'summary' => $this->summary,
                'dateFrom' => $this->dateFrom,
                'dateTo' => $this->dateTo,
                'currency_symbol' => $this->currencySymbol,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
