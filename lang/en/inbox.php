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
        'jump_latest' => 'Jump to latest',
        'sending' => 'Sending…',
        'retry' => 'Tap to retry',
        'reply' => 'Reply',
        'replying_to' => 'Replying to {name}',
        'cancel_reply' => 'Cancel reply',
        'reactions' => [
            'add' => 'Add reaction',
            'react_with' => 'React with {emoji}',
            'pill_label' => '{emoji}, {count} reactions',
        ],
        'attachment' => [
            'unavailable' => 'Attachment unavailable',
            'open_image' => 'Open image',
            'close' => 'Close',
        ],
    ],

    'title' => 'Inbox',
    'subtitle' => 'Tenant messages from WhatsApp and SMS',
    'unread_count' => '({count} unread)',
    'mark_all_read' => 'Mark All as Read',
    'confirm_mark_all_read' => 'Mark all messages as read?',
    'search_placeholder' => 'Search by tenant name, phone, or message...',
    'filter' => [
        'all' => 'All Messages',
        'unread' => 'Unread',
        'processed' => 'Read / Processed',
    ],
    'table' => [
        'tenant' => 'Tenant',
        'message' => 'Message',
        'source' => 'Source',
        'status' => 'Status',
        'time' => 'Time',
        'actions' => 'Actions',
    ],
    'status' => [
        'received' => 'Unread',
        'processed' => 'Read',
        'action_taken' => 'Actioned',
        'ignored' => 'Ignored',
    ],
    'reply_prefix' => 'Re: {subject}',
    'ticket_label' => 'Ticket #{id}',
    'mark_read_title' => 'Mark as read',
    'mark_read' => 'Mark Read',
    'view' => 'View',
    'empty' => [
        'title' => 'No messages',
        'description' => 'When tenants reply to notifications via WhatsApp or SMS, their messages will appear here.',
    ],
    'pagination' => [
        'previous' => 'Previous',
        'next' => 'Next',
        'showing' => 'Showing {from} to {to} of {total} messages',
    ],
];
