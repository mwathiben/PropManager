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
 * Phase-13 BREACH-1: ops/legal alert dispatched the moment
 * KenyaDpaService::initiateBreachNotification creates a SecurityIncident.
 * Before this mailable existed, notifyAdministrators only wrote a
 * Log::info line — a breach could land and no human be paged.
 */
class BreachReportedAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public SecurityIncident $incident
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[BREACH] '.strtoupper($this->incident->severity).' — incident #'.$this->incident->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.breach-reported-alert',
            with: [
                'incident' => $this->incident,
                'odpcEmail' => config('security.kenya_dpa.odpc_email'),
                'hoursToDeadline' => (int) now()->diffInHours($this->incident->notification_deadline, false),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
