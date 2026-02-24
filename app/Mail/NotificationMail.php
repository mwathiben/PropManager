<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Services\Notification\UnsubscribeUrlResolver;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificationMail extends Mailable
{
    public function __construct(
        public string $notificationSubject,
        public string $notificationMessage,
        public ?array $data,
        public User $recipient
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notificationSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.notification',
            with: [
                'subject' => $this->notificationSubject,
                'notificationBody' => $this->notificationMessage,
                'data' => $this->data,
                'recipient' => $this->recipient,
                'unsubscribeUrl' => app(UnsubscribeUrlResolver::class)->resolve($this->recipient),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
