<?php

namespace App\Exceptions\WaterReading;

use App\Exceptions\DomainException;

class WaterReadingException extends DomainException
{
    public function __construct(
        string $message,
        string $errorCode = 'WATER_READING_ERROR',
        array $context = [],
        int $statusCode = 400
    ) {
        parent::__construct($message, $errorCode, $context, $statusCode);
    }
}
