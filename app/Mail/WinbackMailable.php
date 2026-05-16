<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-34 GROWTH-LIFECYCLE-3: post-cancellation winback at day 7
 * (WINBACK10) and day 30 (WINBACK20). Industry benchmark 5-10%
 * reactivation rate for B2B SaaS.
 */
class WinbackMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public string $discountCode,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('growth.lifecycle.winback_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.growth.winback',
            with: [
                'subscription' => $this->subscription,
                'discountCode' => $this->discountCode,
                'plansUrl' => route('subscription.plans'),
            ],
        );
    }
}
