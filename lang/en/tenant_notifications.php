<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant notifications list page. Mirror en/sw/ar.
 */
return [
    'title' => 'My Notifications',
    'unread_count' => '{count} unread',
    'all_caught_up' => 'All caught up!',
    'mark_all_read' => 'Mark All as Read',
    'filters' => [
        'all' => 'All',
        'unread' => 'Unread',
        'read' => 'Read',
    ],
    'empty' => [
        'title' => 'No notifications',
        'unread' => "You've read all your notifications!",
        'all' => "You don't have any notifications yet.",
    ],
    'group' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
    ],
    'type' => [
        'rent_reminder' => 'Rent Reminder',
        'arrears_notice' => 'Arrears Notice',
        'invoice' => 'Invoice',
        'receipt' => 'Receipt',
        'rent_hike' => 'Rent Adjustment',
        'lease_expiry' => 'Lease Expiry',
        'lease_renewal' => 'Lease Renewal',
        'maintenance_notice' => 'Maintenance',
        'general' => 'General',
        'eviction_notice' => 'Eviction Notice',
        'caretaker_invitation' => 'Caretaker Invitation',
        'tenant_invitation' => 'Tenant Invitation',
    ],
    'processing' => 'Processing...',
    'accept' => 'Accept',
    'decline' => 'Decline',
    'confirm_decline' => 'Are you sure you want to decline this invitation?',
];
