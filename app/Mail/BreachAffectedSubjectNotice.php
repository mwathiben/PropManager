<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-13 BREACH-4: Article 34 / Kenya DPA Section 43(2) affected-
 * subject notification. Sent to each individual whose data may have
 * been compromised when the breach is likely to result in high risk
 * to their rights and freedoms. The text discloses: incident date,
 * data categories affected, likely consequences, mitigation steps,
 * and a contact for questions.
 *
 * Triggered explicitly via KenyaDpaService::notifyAffectedSubjects —
 * the regulator-notification path (BreachReportedAlert) does NOT
 * imply this; the controller decides per-incident whether
 * affected-subject paging is required.
 */
class BreachAffectedSubjectNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public SecurityIncident $incident,
        public User $dataSubject,
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Important security notice — recent incident affecting your '.config('app.name', 'PropManager').' account',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.breach-affected-subject-notice',
            with: [
                'incident' => $this->incident,
                'subject' => $this->dataSubject,
                'controllerName' => config('app.name', 'PropManager'),
                'odpcEmail' => config('security.kenya_dpa.odpc_email'),
                'supportEmail' => config('mail.from.address') ?: 'support@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
