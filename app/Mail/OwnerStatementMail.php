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
 * Phase-101 OWNER-FOUNDATION: emails an owner their consolidated statement (PDF
 * attached). The statement array + currency are plain serializable data, so the PDF is
 * re-rendered in the queued job rather than carried as bytes.
 */
class OwnerStatementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $statement  OwnerStatementService::forOwner() output
     */
    public function __construct(
        public array $statement,
        public string $currencySymbol,
        public string $currencyCode,
        public string $landlordName,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        $period = ($this->statement['period']['start'] ?? '').' – '.($this->statement['period']['end'] ?? '');

        return new Envelope(
            subject: __('emails.subjects.owner_statement', ['period' => $period]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.owner-statement',
            with: [
                'statement' => $this->statement,
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
        $pdf = Pdf::loadView('reports.owner-statement-multi', [
            'data' => $this->statement,
            'landlord' => (object) ['name' => $this->landlordName],
            'generated_at' => $this->statement['generated_at'] ?? now()->format('Y-m-d H:i'),
            'currency_symbol' => $this->currencySymbol,
            'currency_code' => $this->currencyCode,
        ]);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'owner-statement.pdf')
                ->withMime('application/pdf'),
        ];
    }

    /**
     * Surface a queued-send failure (the user was already told "queued") so a money
     * document that never went out is debuggable rather than vanishing into failed_jobs.
     */
    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('Owner statement email failed', [
            'owner_id' => $this->statement['owner']['id'] ?? null,
            'period' => $this->statement['period'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
