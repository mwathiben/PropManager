<?php

namespace App\Exceptions\Import;

class InvalidCsvFormatException extends ImportException
{
    public function __construct(string $reason = 'no headers found')
    {
        parent::__construct(
            message: "Invalid CSV file - {$reason}",
            errorCode: 'IMPORT_INVALID_FORMAT',
            context: [
                'reason' => $reason,
            ]
        );
    }
}
