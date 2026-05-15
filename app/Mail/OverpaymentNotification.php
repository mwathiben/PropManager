<?php

namespace App\Mail;

use App\Enums\Currency;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OverpaymentNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
        public Lease $lease,
        public User $tenant,
        public float $overpaymentAmount,
        public float $newWalletBalance
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        $paymentRef = $this->payment->reference ?? $this->payment->id ?? 'unknown';

        return new Envelope(
            subject: __('emails.subjects.overpayment_notice', ['ref' => $paymentRef]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.overpayment-notification',
            with: [
                'payment' => $this->payment,
                'lease' => $this->lease,
                'tenant' => $this->tenant,
                'unit' => $this->lease->unit,
                'overpaymentAmount' => $this->overpaymentAmount,
                'newWalletBalance' => $this->newWalletBalance,
                'currency_symbol' => ($this->payment->currency ?? Currency::default())->symbol(),
                'unsubscribeUrl' => route('notifications.settings'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
