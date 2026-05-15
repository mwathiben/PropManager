<?php

namespace App\Mail;

use App\Models\TenantPaymentVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class PaymentVerificationApproved extends Mailable implements ShouldQueue
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
            subject: __('emails.subjects.payment_verification_approved'),
        );
    }

    public function content(): Content
    {
        $lease = $this->verification->lease;

        return new Content(
            markdown: 'emails.payment-verification-approved',
            with: [
                'verification' => $this->verification,
                'tenant' => $lease->tenant,
                'unit' => $lease->unit,
                'building' => $lease->unit->building,
                'dashboardUrl' => route('dashboard'),
                'currency_symbol' => $lease->unit->building->getEffectiveCurrency()->symbol(),
                'unsubscribeUrl' => URL::temporarySignedRoute(
                    'email.preferences',
                    now()->addDays(30),
                    ['user' => $lease->tenant_id]
                ),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
