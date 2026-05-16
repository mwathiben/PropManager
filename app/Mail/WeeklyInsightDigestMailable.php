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
 * Phase-37 PWA-DIGEST-1: weekly insight digest email backed by
 * InsightDashboardService::landlordSummary. Reuses the Phase-34
 * lifecycle Mailable pattern (afterCommit + ShouldQueue + lang
 * envelope subject + markdown content) so brand styling and
 * Phase-13 PERSONAL-DATA-1 unsubscribe footer auto-apply.
 */
class WeeklyInsightDigestMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $summary  InsightDashboardService::landlordSummary payload
     */
    public function __construct(
        public User $landlord,
        public array $summary,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('pwa.digest.mail_subject', ['landlord' => $this->landlord->name ?? '']),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.insight.weekly-digest',
            with: [
                'landlord' => $this->landlord,
                'summary' => $this->summary,
                'optOutUrl' => route('settings.notifications').'#insight-digest',
            ],
        );
    }
}
