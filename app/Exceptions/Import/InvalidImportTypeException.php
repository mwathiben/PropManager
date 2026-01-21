<?php

namespace App\Exceptions\Import;

class InvalidImportTypeException extends ImportException
{
    public function __construct(string $type)
    {
        parent::__construct(
            message: "Invalid import type: {$type}",
            errorCode: 'IMPORT_INVALID_TYPE',
            context: [
                'type' => $type,
            ]
        );
    }
}
