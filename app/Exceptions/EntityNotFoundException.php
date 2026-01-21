<?php

namespace App\Exceptions;

class EntityNotFoundException extends DomainException
{
    public function __construct(
        string $entityType,
        string|int $identifier,
        ?string $identifierField = null,
        array $context = []
    ) {
        $field = $identifierField ?? 'ID';
        $message = "{$entityType} with {$field} '{$identifier}' not found";

        parent::__construct(
            message: $message,
            errorCode: 'ENTITY_NOT_FOUND',
            context: array_merge([
                'entity_type' => $entityType,
                'identifier' => $identifier,
                'identifier_field' => $field,
            ], $context),
            statusCode: 404
        );
    }
}
