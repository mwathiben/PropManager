<?php

namespace App\Exceptions\Notification;

use App\Exceptions\DomainException;

class NotificationException extends DomainException
{
    public function __construct(
        string $message,
        string $errorCode = 'NOTIFICATION_ERROR',
        array $context = [],
        int $statusCode = 400
    ) {
        parent::__construct($message, $errorCode, $context, $statusCode);
    }
}
