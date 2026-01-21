<?php

namespace App\Exceptions\Import;

class ImportFileException extends ImportException
{
    public function __construct(string $filePath, ?string $reason = null)
    {
        $message = "Unable to open file: {$filePath}";
        if ($reason) {
            $message .= " ({$reason})";
        }

        parent::__construct(
            message: $message,
            errorCode: 'IMPORT_FILE_ERROR',
            context: [
                'file_path' => $filePath,
                'reason' => $reason,
            ]
        );
    }
}
