<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant-invitation management page. Mirror en/sw/ar.
 */
return [
    'title' => 'Tenant Invitations',
    'subtitle' => 'Invite new tenants to your properties',
    'send' => 'Send Invitation',
    'no_vacant' => [
        'title' => 'No Vacant Units',
        'body' => 'All your units are occupied. Free up a unit or add new units to send tenant invitations.',
    ],
    'stats' => [
        'total' => 'Total Invitations',
        'pending' => 'Pending',
        'accepted' => 'Accepted',
    ],
    'status' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'expired' => 'Expired',
    ],
    'table' => [
        'tenant' => 'Tenant',
        'unit' => 'Unit',
        'lease_terms' => 'Lease Terms',
        'status' => 'Status',
        'actions' => 'Actions',
    ],
    'pending_registration' => 'Pending Registration',
    'unit_prefix' => 'Unit',
    'per_month' => '/mo',
    'deposit_label' => 'Deposit:',
    'start_label' => 'Start:',
    'expires_label' => 'Expires:',
    'viewed' => 'Viewed',
    'actions' => [
        'copy' => 'Copy invitation link',
        'resend' => 'Resend invitation',
        'edit' => 'Edit invitation',
        'cancel' => 'Cancel invitation',
        'cancel_btn' => 'Cancel',
    ],
    'empty' => [
        'title' => 'No invitations',
        'filtered' => 'No invitations match this filter.',
        'get_started' => 'Get started by sending a tenant invitation.',
    ],
    'create' => [
        'title' => 'Send Tenant Invitation',
        'sending' => 'Sending...',
    ],
    'edit' => [
        'title' => 'Edit Invitation',
        'saving' => 'Saving...',
        'save' => 'Save Changes',
    ],
    'form' => [
        'unit' => 'Select Unit *',
        'unit_placeholder' => 'Choose a vacant unit...',
        'email' => 'Email Address *',
        'email_placeholder' => 'tenant@example.com',
        'name' => 'Tenant Name',
        'name_placeholder' => 'John Doe',
        'phone' => 'Phone Number',
        'phone_placeholder' => '+254 712 345 678',
        'lease_terms' => 'Lease Terms',
        'rent' => 'Monthly Rent ({currency}) *',
        'service_charge' => 'Service Charge',
        'deposit' => 'Deposit ({currency}) *',
        'start_date' => 'Start Date *',
        'end_date' => 'End Date (Optional)',
        'total_movein' => 'Total Move-in Cost',
        'movein_breakdown' => 'First month rent + service charge + deposit',
        'send_via' => 'Send Invitation Via *',
        'notification_channels' => 'Notification Channels',
    ],
    'channel' => [
        'email' => 'Email',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'not_configured' => '(Not configured)',
    ],
    'confirm' => [
        'resend' => 'Resend this invitation?',
        'cancel' => 'Are you sure you want to cancel this invitation? This cannot be undone.',
    ],
    'alert' => [
        'copied' => 'Invitation link copied to clipboard!',
    ],
];
