<?php

namespace App\Exceptions\Import;

use App\Exceptions\DomainException;

class ImportException extends DomainException
{
    public function __construct(
        string $message,
        string $errorCode = 'IMPORT_ERROR',
        array $context = [],
        int $statusCode = 400
    ) {
        parent::__construct($message, $errorCode, $context, $statusCode);
    }
}
