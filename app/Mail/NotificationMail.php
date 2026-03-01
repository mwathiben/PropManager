<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Services\Notification\UnsubscribeUrlResolver;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class NotificationMail extends Mailable
{
    private ?UnsubscribeUrlResolver $resolverInstance = null;

    public function __construct(
        public string $notificationSubject,
        public string $notificationMessage,
        public ?array $data,
        public User $recipient
    ) {}

    public function headers(): Headers
    {
        $url = $this->resolver()->resolveForHeader($this->recipient);

        if (! $url) {
            return new Headers(text: []);
        }

        $headers = ['List-Unsubscribe' => '<'.$url.'>'];

        if ($this->recipient->isTenant()) {
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(text: $headers);
    }

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
                'unsubscribeUrl' => $this->resolver()->resolve($this->recipient),
            ],
        );
    }

    private function resolver(): UnsubscribeUrlResolver
    {
        return $this->resolverInstance ??= app(UnsubscribeUrlResolver::class);
    }
}
