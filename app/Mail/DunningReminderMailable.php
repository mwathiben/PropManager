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
 * Phase-34 GROWTH-LIFECYCLE-2: past_due payment recovery touch.
 * Sequence: day 1 / day 4 / day 7 after entering past_due, then
 * day 14 = auto-cancellation by the cron.
 */
class DunningReminderMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public int $daysSincePastDue,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('growth.lifecycle.dunning_subject', ['days' => $this->daysSincePastDue]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.growth.dunning',
            with: [
                'subscription' => $this->subscription,
                'daysSincePastDue' => $this->daysSincePastDue,
                'updateCardUrl' => route('subscription.index'),
            ],
        );
    }
}
