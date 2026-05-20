<?php

declare(strict_types=1);

return [
    'thread_created' => 'Message thread created.',
    'message_sent' => 'Message sent.',
    'thread_locked' => 'Thread locked.',
    'thread_archived' => 'Thread archived.',
    'seen' => [
        'label' => 'Seen',
        'mark_all' => 'Mark all as read',
    ],
    'presence' => [
        'online' => 'Online',
        'typing' => '{name} is typing… | {name} are typing…',
    ],
    'search' => [
        'placeholder' => 'Search messages…',
        'title' => 'Search messages',
        'empty' => 'Type at least 3 characters to search.',
        'no_results' => 'No messages match “{term}”.',
        'in_thread' => 'in {title}',
    ],
    'scan' => [
        'hint' => 'Attachments are scanned before sending.',
        'blocked' => 'An attachment was blocked by the virus scanner and not sent.',
        'unavailable' => 'Attachment scanning is temporarily unavailable. Please try again shortly.',
    ],
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

    'chat' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'unread' => 'Unread messages',
        'sent' => 'Sent',
        'placeholder' => 'Type a message…',
        'send' => 'Send',
        'attach' => 'Attach files',
        'body_label' => 'Message body',
        'locked' => 'This thread is {status} and cannot accept new messages.',
        'chars_remaining' => '{count} character left | {count} characters left',
    ],
];
