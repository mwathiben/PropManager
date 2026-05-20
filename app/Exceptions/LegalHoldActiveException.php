<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Phase-68 HOLD-GUARD-1: thrown when something attempts to delete a
 * subject under an active legal hold. A hold is a court-ordered
 * preservation directive (Kenya DPA Section 30 / GDPR 6(1)(c)) — the
 * data MUST survive every delete path, not just the retention cron.
 * Rendered uniformly (friendly error + blocked-deletion gauge) in
 * bootstrap/app.php.
 */
class LegalHoldActiveException extends RuntimeException
{
    public function __construct(
        public readonly string $subjectType,
        public readonly int $subjectId,
    ) {
        parent::__construct("Subject {$subjectType}#{$subjectId} is under an active legal hold and cannot be deleted.");
    }
}
