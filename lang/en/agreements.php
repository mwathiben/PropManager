<?php

declare(strict_types=1);

/**
 * Slice-2 PR-2.2: management-agreement composer. Mirror en/sw/ar.
 */
return [
    'index' => [
        'title' => 'Management agreements',
        'subtitle' => 'Agreements you manage on owners\' behalf.',
        'new' => 'New agreement',
        'none' => 'No agreements yet. Compose your first to set an owner\'s terms.',
        'owner' => 'Owner',
        'status' => 'Status',
        'created' => 'Created',
    ],
    'compose' => [
        'title' => 'New management agreement',
        'owner' => 'Property owner',
        'owner_placeholder' => 'Select an owner...',
        'clauses' => 'Clauses',
        'clauses_hint' => 'Pick the terms. Each is explained; the fee clause sets what you charge.',
        'include' => 'Include',
        'required_clause' => 'Required',
        'fee_type' => 'Type',
        'fee_base' => 'Base',
        'fee_value' => 'Value',
        'fee_cadence' => 'Cadence',
        'preview' => 'Agreement preview',
        'preview_empty' => 'Select clauses to see the agreement.',
        'submit' => 'Save draft',
        'cancel' => 'Cancel',
    ],
    'show' => [
        'owner' => 'Owner',
        'status' => 'Status',
        'hash' => 'Document hash',
        'back' => 'Back to agreements',
        'draft_note' => 'Draft — not yet sent to the owner for signature.',
    ],
    'status' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'signed' => 'Signed',
        'active' => 'Active',
        'amending' => 'Amending',
        'terminated' => 'Terminated',
    ],
    'draft_created' => 'Draft agreement created.',
    'errors' => [
        'duplicate_binding' => 'An agreement may include each kind of clause only once.',
        'invalid_fee' => 'The management-fee terms are invalid — check the type and value.',
        'missing_param' => 'Fill in the ":field" detail for this clause.',
        'invalid_option' => 'The ":field" value is not allowed for this clause.',
    ],
];
