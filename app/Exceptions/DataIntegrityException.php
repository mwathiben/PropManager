<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Phase-18 DATA-3 + DATA-6: thrown when an operation would violate a
 * data-integrity invariant (e.g., deleting a Unit while an active
 * Lease still references it, or soft-deleting a Property with live
 * descendants when the operator hasn't opted into cascade).
 *
 * Concrete subclass of DomainException so the App\Exceptions handler
 * renders a 422 JSON response with a stable error code.
 */
class DataIntegrityException extends DomainException
{
    public function __construct(string $message, string $errorCode, array $context = [])
    {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            context: $context,
            statusCode: 422,
        );
    }
}
