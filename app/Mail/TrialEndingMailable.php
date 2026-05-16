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
 * Phase-34 GROWTH-LIFECYCLE-1: trial-ending reminder at -3 / -1 / 0.
 * Subject + body localised via lang/{en,sw}/growth.php so the email
 * matches the recipient's HasLocalePreference.
 */
class TrialEndingMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('growth.lifecycle.trial_ending_subject', ['days' => $this->daysRemaining]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.growth.trial-ending',
            with: [
                'subscription' => $this->subscription,
                'daysRemaining' => $this->daysRemaining,
                'upgradeUrl' => route('subscription.plans'),
            ],
        );
    }
}
