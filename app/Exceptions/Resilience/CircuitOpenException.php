<?php

declare(strict_types=1);

namespace App\Exceptions\Resilience;

use RuntimeException;

class CircuitOpenException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $endpoint,
        public readonly int $cooldownSecondsRemaining,
    ) {
        parent::__construct(sprintf(
            'Circuit open for %s %s (cooldown %ds remaining)',
            $provider,
            $endpoint,
            $cooldownSecondsRemaining,
        ));
    }
}
