<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\OnboardingProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-34 GROWTH-LIFECYCLE-3: fires when OnboardingProgress.
 * last_touched_at is older than 3 days AND current_step is not
 * the final step (landlord stalled mid-funnel).
 */
class ActivationNudgeMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public OnboardingProgress $progress,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('growth.lifecycle.activation_nudge_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.growth.activation-nudge',
            with: [
                'progress' => $this->progress,
                'resumeUrl' => route('onboarding.create'),
            ],
        );
    }
}
