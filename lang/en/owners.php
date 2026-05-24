<?php

declare(strict_types=1);

/**
 * Phase-101 OWNER-FOUNDATION. Mirror en / sw / ar exactly (parity-checked).
 */
return [
    'title' => 'Property Owners',
    'subtitle' => 'The owners you manage properties for',
    'add' => 'Add owner',
    'edit' => 'Edit owner',
    'none' => 'No owners yet. Add the first owner you manage properties for.',
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'id_number' => 'ID / registration number',
        'notes' => 'Notes',
        'active' => 'Active',
        'properties' => 'Properties',
    ],
    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'assign' => 'Assign',
        'unassign' => 'Unassign',
        'email_statement' => 'Email statement',
        'download_statement' => 'Download statement',
        'invite' => 'Invite to portal',
    ],
    'assign' => [
        'title' => 'Properties',
        'owner' => 'Owner',
        'unassigned' => 'Unassigned',
    ],
    'delete_confirm' => 'Delete this owner? Their properties stay, just unassigned.',
    'messages' => [
        'created' => 'Owner added.',
        'updated' => 'Owner updated.',
        'deleted' => 'Owner deleted; their properties were unassigned.',
        'assigned' => 'Property assigned to the owner.',
        'unassigned' => 'Property owner cleared.',
        'statement_sent' => 'Statement is being emailed to :email.',
        'statement_no_email' => 'This owner has no email address on file.',
    ],
    'invite' => [
        'sent' => 'Invitation sent to the owner.',
        'no_email' => 'Add an email address before inviting this owner.',
        'email_taken' => 'A user with that email already exists.',
        'already_pending' => 'An invitation is already pending for this owner.',
        'used' => 'This invitation has already been used.',
        'expired' => 'This invitation has expired.',
        'revoked' => 'This invitation is no longer valid.',
        'failed' => 'Could not complete the invitation. Please try again.',
        'welcome' => 'Welcome! Here are the properties managed for you.',
    ],
    'accept' => [
        'title' => 'Set up your owner login',
        'invited_by' => 'Invited by',
        'name' => 'Your name',
        'mobile' => 'Mobile number',
        'password' => 'Password',
        'password_confirm' => 'Confirm password',
        'submit' => 'Create my login',
    ],
    'portal' => [
        'dashboard_title' => 'My Properties',
        'dashboard_subtitle' => 'The properties managed on your behalf',
        'statements_title' => 'My Statements',
        'no_properties' => 'No properties are assigned to you yet.',
        'occupancy' => 'Occupancy',
        'units' => 'units',
        'rent_roll' => 'Monthly rent roll',
        'arrears' => 'Outstanding arrears',
        'collected' => 'Collected',
        'expenses' => 'Expenses',
        'net' => 'Net to you',
        'download' => 'Download statement (PDF)',
        'period' => 'Period',
    ],
];
