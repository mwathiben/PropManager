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
    'accept' => [
        'head_title' => 'Accept Invitation',
        'invalid_title' => 'Invalid Invitation',
        'go_to_login' => 'Go to Login',
        'youre_invited' => "You're Invited!",
        'join_as' => 'Join as a Property Caretaker',
        'invited_by' => 'Invited by',
        'property' => 'Property',
        'email' => 'Email',
        'expires_on' => 'This invitation expires on',
        'full_name' => 'Full Name',
        'name_placeholder' => 'John Doe',
        'mobile_number' => 'Mobile Number (Optional)',
        'phone_placeholder' => '+254 712 345 678',
        'password' => 'Password',
        'password_placeholder' => 'Minimum 8 characters',
        'confirm_password' => 'Confirm Password',
        'confirm_password_placeholder' => 'Re-enter password',
        'terms_notice' => "By accepting this invitation, you'll create a caretaker account and gain access to manage operations for {property}.",
        'creating' => 'Creating Account...',
        'submit' => 'Accept Invitation & Create Account',
        'already_have_account' => 'Already have an account?',
        'login_here' => 'Login here',
    ],
    'accept_existing' => [
        'head_title' => 'Accept Caretaker Invitation',
        'title' => 'Caretaker Invitation',
        'subtitle' => "You've been invited to become a property caretaker",
        'invited_by' => 'Invited by',
        'property' => 'Property',
        'expires_on' => 'Expires on',
        'info_intro' => 'By accepting this invitation, your account will be converted to a',
        'info_role' => 'caretaker',
        'info_middle' => "role. You'll gain access to manage operations for",
        'processing' => 'Processing...',
        'accept' => 'Accept Invitation',
        'decline' => 'Decline',
        'decline_confirm' => 'Are you sure you want to decline this invitation?',
    ],
];
