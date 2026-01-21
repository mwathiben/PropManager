<?php

namespace App\Exceptions\Notification;

class RecipientNotFoundException extends NotificationException
{
    public function __construct(?int $recipientId = null, ?string $notificationType = null)
    {
        parent::__construct(
            message: 'Recipient not found',
            errorCode: 'NOTIFICATION_NO_RECIPIENT',
            context: array_filter([
                'recipient_id' => $recipientId,
                'notification_type' => $notificationType,
            ]),
            statusCode: 404
        );
    }
}
