<?php

declare(strict_types=1);

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use Tests\Support\Contracts\MailCapturePort;

class FakeMailCapture implements MailCapturePort
{
    /** @var array<int, array<string, mixed>> */
    private array $messages = [];

    /** @var array<string, array<string, string[]>> */
    private array $headers = [];

    /** @param array<string, string[]> $headers */
    public function addMessage(array $message, array $headers = []): void
    {
        array_unshift($this->messages, $message);

        if (isset($message['ID'])) {
            $this->headers[$message['ID']] = $headers;
        }
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public function getLatestMessage(): ?array
    {
        return $this->messages[0] ?? null;
    }

    public function getMessageHtml(string $id): string
    {
        foreach ($this->messages as $message) {
            if (($message['ID'] ?? null) === $id) {
                return $message['HTML'] ?? '';
            }
        }

        return '';
    }

    public function getMessageHeaders(string $id): array
    {
        return $this->headers[$id] ?? [];
    }

    public function searchByRecipient(string $email): array
    {
        return array_values(array_filter($this->messages, function (array $message) use ($email): bool {
            foreach ($message['To'] ?? [] as $recipient) {
                if (($recipient['Address'] ?? null) === $email) {
                    return true;
                }
            }

            return false;
        }));
    }

    public function searchBySubject(string $subject): array
    {
        return array_values(array_filter($this->messages, function (array $message) use ($subject): bool {
            return str_contains($message['Subject'] ?? '', $subject);
        }));
    }

    public function deleteAll(): void
    {
        $this->messages = [];
        $this->headers = [];
    }

    public function getMessageLinks(string $id): array
    {
        $html = $this->getMessageHtml($id);

        if ($html === '') {
            return [];
        }

        return $this->extractLinksFromHtml($html);
    }

    public function waitForMessage(string $to, int $timeoutSeconds = 5): ?array
    {
        $results = $this->searchByRecipient($to);

        return $results[0] ?? null;
    }

    /** @return string[] */
    private function extractLinksFromHtml(string $html): array
    {
        $dom = new DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $links = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            if ($anchor instanceof DOMElement && $anchor->hasAttribute('href')) {
                $links[] = $anchor->getAttribute('href');
            }
        }

        return $links;
    }
}
