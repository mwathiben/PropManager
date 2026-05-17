<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\OnboardingSession;
use App\Onboarding\OnboardingFlow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-47 MAIL-DISPATCH-1: nudge a landlord/caretaker/tenant back into the
 * wizard. Reuses the Phase-37 PWA-DIGEST-1 Mailable pattern (afterCommit +
 * ShouldQueue + lang envelope subject + markdown content) so brand styling
 * and the Phase-13 PERSONAL-DATA-1 unsubscribe footer auto-apply.
 *
 * The Phase-46 PROGRESS-RESUME-1 signed URL is supplied by the caller
 * (onboarding:nudge-stalled cron); the Mailable does not regenerate it.
 */
class OnboardingResumeMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resumeUrl,
        public OnboardingSession $session,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('onboarding.nudge.subject'),
        );
    }

    public function content(): Content
    {
        $flow = OnboardingFlow::forRole($this->session->role);
        $stepLabel = $flow->stepLabel($this->session->current_step);

        // Phase-51 PHASE-46-WIZARD-STYLE-3: explicit plain-text view so
        // text-only mail clients + accessibility readers get a clean,
        // logically-ordered rendering instead of the Laravel auto-converted
        // markdown.
        return new Content(
            markdown: 'emails.onboarding.resume',
            text: 'emails.onboarding.resume-text',
            with: [
                'resumeUrl' => $this->resumeUrl,
                'session' => $this->session,
                'stepLabel' => $stepLabel,
            ],
        );
    }
}
