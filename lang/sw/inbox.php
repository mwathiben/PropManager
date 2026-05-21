<?php

declare(strict_types=1);

return [
    'thread_created' => '[TODO-sw] Message thread created.',
    'message_sent' => '[TODO-sw] Message sent.',
    'thread_locked' => '[TODO-sw] Thread locked.',
    'thread_archived' => '[TODO-sw] Thread archived.',
    'seen' => [
        'label' => '[TODO-sw] Seen',
        'mark_all' => '[TODO-sw] Mark all as read',
    ],
    'presence' => [
        'online' => '[TODO-sw] Online',
        'typing' => '[TODO-sw] {name} is typing… | [TODO-sw] {name} are typing…',
    ],
    'search' => [
        'placeholder' => '[TODO-sw] Search messages…',
        'title' => '[TODO-sw] Search messages',
        'empty' => '[TODO-sw] Type at least 3 characters to search.',
        'no_results' => '[TODO-sw] No messages match “{term}”.',
        'in_thread' => '[TODO-sw] in {title}',
    ],
    'scan' => [
        'hint' => '[TODO-sw] Attachments are scanned before sending.',
        'blocked' => '[TODO-sw] An attachment was blocked by the virus scanner and not sent.',
        'unavailable' => '[TODO-sw] Attachment scanning is temporarily unavailable. Please try again shortly.',
    ],
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

    'chat' => [
        'today' => '[TODO-sw] Today',
        'yesterday' => '[TODO-sw] Yesterday',
        'unread' => '[TODO-sw] Unread messages',
        'sent' => '[TODO-sw] Sent',
        'placeholder' => '[TODO-sw] Type a message…',
        'send' => '[TODO-sw] Send',
        'attach' => '[TODO-sw] Attach files',
        'body_label' => '[TODO-sw] Message body',
        'locked' => '[TODO-sw] This thread is {status} and cannot accept new messages.',
        'chars_remaining' => '[TODO-sw] {count} character left | [TODO-sw] {count} characters left',
        'jump_latest' => '[TODO-sw] Jump to latest',
        'sending' => '[TODO-sw] Sending…',
        'retry' => '[TODO-sw] Tap to retry',
        'reply' => '[TODO-sw] Reply',
        'replying_to' => '[TODO-sw] Replying to {name}',
        'cancel_reply' => '[TODO-sw] Cancel reply',
        'reactions' => [
            'add' => '[TODO-sw] Add reaction',
            'react_with' => '[TODO-sw] React with {emoji}',
            'pill_label' => '[TODO-sw] {emoji}, {count} reactions',
        ],
        'attachment' => [
            'unavailable' => '[TODO-sw] Attachment unavailable',
            'open_image' => '[TODO-sw] Open image',
            'close' => '[TODO-sw] Close',
        ],
    ],
];
