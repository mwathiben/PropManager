<?php

declare(strict_types=1);

namespace Tests\Support\Exceptions;

use RuntimeException;
use Throwable;

class MailpitConnectionException extends RuntimeException
{
    /** @var array<string, mixed> */
    public readonly array $context;

    /** @param array<string, mixed> $context */
    public function __construct(string $message, array $context = [], ?Throwable $previous = null)
    {
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    public static function connectionFailed(string $baseUrl, ?Throwable $previous = null): self
    {
        return new self(
            'Failed to connect to Mailpit',
            ['base_url' => $baseUrl],
            $previous,
        );
    }

    public static function messageNotFound(string $id): self
    {
        return new self(
            "Mailpit message not found: {$id}",
            ['message_id' => $id],
        );
    }
}
