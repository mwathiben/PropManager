<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Phase-30 INT-PERIOD-LOCK-2: thrown by Invoice/Payment/Expense
 * booted() hooks when a write would mutate a row inside a closed
 * accounting period. The HTTP layer should translate this to 423
 * Locked.
 */
class AccountingPeriodLockedException extends RuntimeException
{
    public static function forModel(string $modelClass, string $effectiveDate): self
    {
        return new self(sprintf(
            'Cannot write %s: effective date %s falls inside a closed accounting period.',
            class_basename($modelClass),
            $effectiveDate,
        ));
    }
}
