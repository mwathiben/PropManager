<?php

namespace App\Exceptions\Notification;

class ChannelSendException extends NotificationException
{
    public function __construct(string $channel, ?string $reason = null)
    {
        $message = "Failed to send via fallback channel: {$channel}";
        if ($reason) {
            $message .= " ({$reason})";
        }

        parent::__construct(
            message: $message,
            errorCode: 'NOTIFICATION_CHANNEL_FAILED',
            context: [
                'channel' => $channel,
                'reason' => $reason,
            ],
            statusCode: 503
        );
    }
}
