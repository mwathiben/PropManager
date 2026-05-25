<?php

declare(strict_types=1);

/**
 * i18n migration: operations hub team-management tab. Mirror en/sw/ar.
 */
return [
    'header_title' => 'Team Members',
    'header_subtitle' => 'Manage caretakers and property managers',
    'invite_caretaker' => 'Invite Caretaker',
    'active_caretakers' => 'Active Caretakers',
    'no_active_caretakers' => 'No active caretakers',
    'pending_invitations' => 'Pending Invitations',
    'buildings_count' => '{count} buildings',
    'expires' => 'Expires: {date}',
    'status' => [
        'pending' => 'pending',
        'accepted' => 'accepted',
        'expired' => 'expired',
        'declined' => 'declined',
    ],
    'no_pending_invitations' => 'No pending invitations',
    'actions' => [
        'copy_link' => 'Copy Link',
        'resend' => 'Resend',
        'cancel' => 'Cancel',
    ],
    'modal' => [
        'title' => 'Invite Caretaker',
        'name' => 'Name',
        'email' => 'Email',
        'assign_buildings' => 'Assign to Buildings',
        'cancel' => 'Cancel',
        'send_invitation' => 'Send Invitation',
    ],
    'confirm' => [
        'resend' => 'Resend this invitation?',
        'cancel' => 'Cancel this invitation?',
        'remove_caretaker' => 'Remove this caretaker? They will lose access to your properties.',
    ],
    'toast' => [
        'accepted' => '{name} accepted the invitation!',
        'sent' => 'Invitation sent successfully!',
        'resent' => 'Invitation resent!',
        'link_copied' => 'Invitation link copied to clipboard!',
        'copy_failed' => 'Failed to copy link',
    ],
];
