<?php

namespace App\Exceptions\Import;

class DuplicateEntityException extends ImportException
{
    public function __construct(string $entityType, string|int $identifier, ?string $location = null)
    {
        $message = "{$entityType} '{$identifier}' already exists";
        if ($location) {
            $message .= " in {$location}";
        }

        parent::__construct(
            message: $message,
            errorCode: 'IMPORT_DUPLICATE_ENTITY',
            context: [
                'entity_type' => $entityType,
                'identifier' => $identifier,
                'location' => $location,
            ],
            statusCode: 409
        );
    }
}
