<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-68 STALE-SWEEP-2: nudges the owning landlord to confirm or
 * release legal holds that have been active past the stale threshold
 * (litigation likely resolved). ShouldQueue + afterCommit mirrors the
 * Phase-54 VendorAssignmentMailable / Phase-47 OnboardingResumeMailable
 * pattern. The hold summaries are passed as a plain array (not models)
 * to keep the queue payload small and locale-stable.
 *
 * @phpstan-type StaleHoldSummary array{type: string, id: int, reason: string, held_at: ?string, days_held: int}
 */
class StaleHoldReminderMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{type: string, id: int, reason: string, held_at: ?string, days_held: int}>  $holds
     */
    public function __construct(
        public User $landlord,
        public array $holds,
    ) {
        $this->afterCommit = true;
        $this->locale($this->resolveLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('legal_holds.stale.subject', ['count' => count($this->holds)]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.legal-hold.stale-reminder',
            with: [
                'landlord' => $this->landlord,
                'holds' => $this->holds,
            ],
        );
    }

    private function resolveLocale(): string
    {
        if (method_exists($this->landlord, 'preferredLocale')) {
            $locale = $this->landlord->preferredLocale();
            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        return (string) config('app.fallback_locale', 'en');
    }
}
