<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-104 OWNER-REMITTANCE-NOTIFY: emails a property owner a remittance advice (PDF) for
 * a payout the manager has made. Payout + balance are plain serializable arrays, so the PDF
 * is re-rendered in the queued job rather than carried as bytes (mirrors OwnerStatementMail).
 */
class OwnerPayoutMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payout  id/amount/currency_code/paid_on/method/reference/notes
     * @param  array<string, mixed>  $summary  OwnerLedgerService::summary() output
     */
    public function __construct(
        public array $payout,
        public array $summary,
        public string $currencySymbol,
        public string $ownerName,
        public string $landlordName,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.subjects.owner_payout_advice', ['date' => $this->payout['paid_on'] ?? '']),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.owner-payout',
            with: [
                'payout' => $this->payout,
                'ownerName' => $this->ownerName,
                'landlordName' => $this->landlordName,
                'currencySymbol' => $this->currencySymbol,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('reports.owner-payout-advice', [
            'payout' => $this->payout,
            'summary' => $this->summary,
            'ownerName' => $this->ownerName,
            'landlordName' => $this->landlordName,
            'currency_symbol' => $this->currencySymbol,
            'generated_at' => now()->format('Y-m-d H:i'),
        ]);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'remittance-advice.pdf')
                ->withMime('application/pdf'),
        ];
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('Owner payout remittance email failed', [
            'payout_id' => $this->payout['id'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
