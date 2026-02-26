<?php

declare(strict_types=1);

namespace Tests\Support\Contracts;

interface MailCapturePort
{
    /** @return array<int, array<string, mixed>> */
    public function messages(): array;

    /** @return array<string, mixed>|null */
    public function getLatestMessage(): ?array;

    public function getMessageHtml(string $id): string;

    /** @return array<string, string[]> */
    public function getMessageHeaders(string $id): array;

    /** @return array<int, array<string, mixed>> */
    public function searchByRecipient(string $email): array;

    /** @return array<int, array<string, mixed>> */
    public function searchBySubject(string $subject): array;

    public function deleteAll(): void;

    /** @return string[] */
    public function getMessageLinks(string $id): array;

    /** @return array<string, mixed>|null */
    public function waitForMessage(string $to, int $timeoutSeconds = 5): ?array;
}
