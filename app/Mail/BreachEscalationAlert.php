<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SecurityIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-13 BREACH-3: 72-hour SLA escalation. Dispatched by the
 * breach:escalate-overdue scheduled command for any SecurityIncident
 * whose notification_deadline has passed without odpc_notified_at being
 * set. Section 43 of the Kenya DPA / Article 33 of GDPR require
 * regulator notification within 72 hours; missing the deadline is the
 * single highest-fine exposure in this audit.
 */
class BreachEscalationAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public SecurityIncident $incident,
        public string $stage
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        $tag = $this->stage === 'overdue' ? 'OVERDUE' : 'IMMINENT';

        return new Envelope(
            subject: "[BREACH-{$tag}] incident #{$this->incident->id} — Section 43 / Article 33 SLA",
        );
    }

    public function content(): Content
    {
        $hoursDelta = (int) now()->diffInHours($this->incident->notification_deadline, false);

        return new Content(
            markdown: 'emails.breach-escalation-alert',
            with: [
                'incident' => $this->incident,
                'stage' => $this->stage,
                'hoursDelta' => $hoursDelta,
                'odpcEmail' => config('security.kenya_dpa.odpc_email'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
