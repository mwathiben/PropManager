<?php

declare(strict_types=1);

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\Contracts\MailCapturePort;
use Tests\Support\Exceptions\MailpitConnectionException;

class MailpitClient implements MailCapturePort
{
    public function __construct(
        private readonly string $baseUrl = 'http://localhost:8025/api/v1',
    ) {}

    public function messages(): array
    {
        $response = $this->request(fn (PendingRequest $http) => $http->get('messages'));

        return $response['messages'] ?? [];
    }

    public function getLatestMessage(): ?array
    {
        $messages = $this->messages();

        return $messages[0] ?? null;
    }

    public function getMessageHtml(string $id): string
    {
        $response = $this->request(fn (PendingRequest $http) => $http->get("message/{$id}"));

        return $response['HTML'] ?? '';
    }

    public function getMessageHeaders(string $id): array
    {
        return $this->request(fn (PendingRequest $http) => $http->get("message/{$id}/headers"));
    }

    public function searchByRecipient(string $email): array
    {
        $response = $this->request(
            fn (PendingRequest $http) => $http->get('search', ['query' => "to:{$email}"]),
        );

        return $response['messages'] ?? [];
    }

    public function searchBySubject(string $subject): array
    {
        $response = $this->request(
            fn (PendingRequest $http) => $http->get('search', ['query' => "subject:{$subject}"]),
        );

        return $response['messages'] ?? [];
    }

    public function deleteAll(): void
    {
        $this->request(fn (PendingRequest $http) => $http->delete('messages'));
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
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $messages = $this->searchByRecipient($to);

            if ($messages !== []) {
                return $messages[0];
            }

            usleep(200_000);
        }

        return null;
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

    /**
     * @throws MailpitConnectionException
     */
    private function request(callable $callback): array
    {
        try {
            $http = Http::baseUrl($this->baseUrl)->timeout(5)->retry(3, 200, throw: false);
            $response = $callback($http);

            return is_array($response->json()) ? $response->json() : [];
        } catch (ConnectionException $e) {
            throw MailpitConnectionException::connectionFailed($this->baseUrl, $e);
        }
    }
}
