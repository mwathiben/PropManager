<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker-invitation management page. Mirror en/sw/ar.
 */
return [
    'title' => 'Caretaker Invitations',
    'subtitle' => 'Invite and manage caretakers for your properties',
    'send' => 'Send Invitation',
    'table' => [
        'email' => 'Caretaker Email',
        'property' => 'Property',
        'sent_date' => 'Sent Date',
        'status' => 'Status',
        'actions' => 'Actions',
    ],
    'accepted_at' => 'Accepted {date}',
    'actions' => [
        'copy' => 'Copy Link',
        'copy_title' => 'Copy invitation link',
        'resend' => 'Resend',
        'resend_title' => 'Resend invitation',
        'cancel' => 'Cancel',
        'cancel_title' => 'Cancel invitation',
    ],
    'empty' => [
        'title' => 'No invitations sent',
        'description' => 'Get started by sending an invitation to a caretaker.',
        'action' => 'Send First Invitation',
    ],
    'modal' => [
        'title' => 'Send Caretaker Invitation',
        'email' => 'Email Address',
        'email_placeholder' => 'caretaker@example.com',
        'property' => 'Property',
        'notice' => 'The caretaker will receive an email with a link to accept the invitation and create their account. Invitations expire after 30 days.',
        'cancel' => 'Cancel',
        'sending' => 'Sending...',
    ],
    'toast' => [
        'title' => 'Invitation Accepted!',
        'message' => '{name} accepted the invitation for {property}',
    ],
    'confirm' => [
        'resend' => 'Resend this invitation?',
        'cancel' => 'Are you sure you want to cancel this invitation?',
    ],
    'alert' => [
        'copied' => 'Invitation link copied to clipboard!',
    ],
];
