<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\WebhookDeadLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FailedWebhookAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public WebhookDeadLetter $deadLetter
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Webhook Failure Alert - '.ucfirst($this->deadLetter->provider),
        );
    }

    public function content(): Content
    {
        $isRetryable = $this->deadLetter->error_class === WebhookDeadLetter::ERROR_TRANSIENT;

        return new Content(
            markdown: 'emails.failed-webhook-alert',
            with: [
                'provider' => $this->deadLetter->provider,
                'eventType' => $this->deadLetter->event_type,
                'errorReason' => $this->deadLetter->error_reason,
                'errorClass' => $this->deadLetter->error_class,
                'createdAt' => $this->deadLetter->created_at,
                'isRetryable' => $isRetryable,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
