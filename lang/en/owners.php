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
];
