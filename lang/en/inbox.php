<?php

declare(strict_types=1);

return [
    'thread_created' => 'Message thread created.',
    'message_sent' => 'Message sent.',
    'thread_locked' => 'Thread locked.',
    'thread_archived' => 'Thread archived.',
    'attachment' => [
        'invalid_mime' => 'Attachment type is not allowed.',
        'too_large' => 'Attachment exceeds the 5 MB size limit.',
    ],
    'message' => [
        'spam_rejected' => 'Message rejected as possible spam. Please review and resend.',
        'deleted_by_sender' => 'Message deleted by sender.',
        'thread_locked_by_landlord' => 'Thread locked by landlord.',
        'thread_unlocked_by_landlord' => 'Thread unlocked by landlord.',
    ],
    'notification' => [
        'subject' => 'New message from :sender',
        'sender_unknown' => 'Property team',
    ],
];
