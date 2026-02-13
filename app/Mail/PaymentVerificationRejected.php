<?php

namespace App\Mail;

use App\Models\TenantPaymentVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentVerificationRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantPaymentVerification $verification
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Verification Issue - Action Required',
        );
    }

    public function content(): Content
    {
        $lease = $this->verification->lease;

        return new Content(
            markdown: 'emails.payment-verification-rejected',
            with: [
                'verification' => $this->verification,
                'tenant' => $lease->tenant,
                'unit' => $lease->unit,
                'rejectionReason' => $this->verification->rejection_reason,
                'resubmitUrl' => route('tenant.payment-required'),
                'currency_symbol' => $lease->unit->building->getEffectiveCurrency()->symbol(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
