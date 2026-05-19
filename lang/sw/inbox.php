<?php

declare(strict_types=1);

return [
    'thread_created' => '[TODO-sw] Message thread created.',
    'message_sent' => '[TODO-sw] Message sent.',
    'thread_locked' => '[TODO-sw] Thread locked.',
    'thread_archived' => '[TODO-sw] Thread archived.',
    'attachment' => [
        'invalid_mime' => '[TODO-sw] Attachment type is not allowed.',
        'too_large' => '[TODO-sw] Attachment exceeds the 5 MB size limit.',
    ],
    'message' => [
        'spam_rejected' => '[TODO-sw] Message rejected as possible spam. Please review and resend.',
        'deleted_by_sender' => '[TODO-sw] Message deleted by sender.',
        'thread_locked_by_landlord' => '[TODO-sw] Thread locked by landlord.',
        'thread_unlocked_by_landlord' => '[TODO-sw] Thread unlocked by landlord.',
    ],
    'notification' => [
        'subject' => '[TODO-sw] New message from :sender',
        'sender_unknown' => '[TODO-sw] Property team',
    ],
];
