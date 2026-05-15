<?php

namespace App\Mail;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class PaymentReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Payment $payment,
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
            subject: __('emails.subjects.payment_received', ['number' => $this->invoice->invoice_number]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-received',
            with: [
                'payment' => $this->payment,
                'invoice' => $this->invoice,
                'tenant' => $this->invoice->lease->tenant,
                'unit' => $this->invoice->lease->unit,
                'currency_symbol' => ($this->payment->currency ?? Currency::default())->symbol(),
                'unsubscribeUrl' => URL::temporarySignedRoute(
                    'email.preferences',
                    now()->addDays(30),
                    ['user' => $this->invoice->lease->tenant_id]
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
