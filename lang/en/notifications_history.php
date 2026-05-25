<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications history tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'Search by recipient or subject...',
    'clear' => 'Clear',
    'status_options' => [
        'all' => 'All Statuses',
        'pending' => 'Pending',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'read' => 'Read',
        'failed' => 'Failed',
    ],
    'channel_options' => [
        'all' => 'All Channels',
        'email' => 'Email',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'push' => 'Push',
    ],
    'type_options' => [
        'all' => 'All Types',
        'rent_reminder' => 'Rent Reminder',
        'arrears_notice' => 'Arrears Notice',
        'invoice' => 'Invoice',
        'receipt' => 'Receipt',
        'rent_hike' => 'Rent Hike',
        'lease_expiry' => 'Lease Expiry',
        'general' => 'General',
    ],
    'table' => [
        'channel' => 'Channel',
        'recipient' => 'Recipient',
        'subject' => 'Subject',
        'type' => 'Type',
        'status' => 'Status',
        'sent_at' => 'Sent At',
        'actions' => 'Actions',
    ],
    'unknown' => 'Unknown',
    'actions' => [
        'view_details' => 'View Details',
        'resend' => 'Resend',
    ],
    'empty' => [
        'title' => 'No Notifications Found',
        'filtered' => 'Try adjusting your filters',
        'default' => 'Notifications will appear here once sent',
    ],
    'pagination' => [
        'showing' => 'Showing {from} to {to} of {total} results',
    ],
    'detail' => [
        'title' => 'Notification Details',
        'subject' => 'Subject',
        'message' => 'Message',
        'type' => 'Type',
        'channel' => 'Channel',
        'sent_at' => 'Sent At',
        'delivered_at' => 'Delivered At',
        'error' => 'Error',
    ],
    'close' => 'Close',
    'confirm' => [
        'resend' => 'Resend this notification?',
    ],
];
