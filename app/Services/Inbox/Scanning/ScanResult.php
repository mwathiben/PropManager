<?php

declare(strict_types=1);

namespace App\Services\Inbox\Scanning;

/**
 * Phase-67 ATTACHMENT-SCAN-1: the outcome of scanning one file.
 * `error` is distinct from `infected` so the caller can apply a
 * fail-open/fail-closed policy when the scanner itself is unavailable.
 */
final class ScanResult
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    public const ERROR = 'error';

    public function __construct(
        public readonly string $status,
        public readonly ?string $signature = null,
    ) {}

    public static function clean(): self
    {
        return new self(self::CLEAN);
    }

    public static function infected(?string $signature = null): self
    {
        return new self(self::INFECTED, $signature);
    }

    public static function error(?string $detail = null): self
    {
        return new self(self::ERROR, $detail);
    }

    public function isClean(): bool
    {
        return $this->status === self::CLEAN;
    }

    public function isInfected(): bool
    {
        return $this->status === self::INFECTED;
    }

    public function isError(): bool
    {
        return $this->status === self::ERROR;
    }
}
