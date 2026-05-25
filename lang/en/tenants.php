<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant detail page. Mirror en/sw/ar.
 */
return [
    'show' => [
        'head_title' => 'Tenant: {name}',
        'back_to_tenants' => 'Back to Tenants',
        'message' => 'Message',
        'edit_profile' => 'Edit Profile',

        'sections' => [
            'overview' => 'Overview',
            'lease' => 'Lease Details',
            'payments' => 'Payments',
            'documents' => 'Documents',
            'notes' => 'Notes',
            'contacts' => 'Emergency Contacts',
            'activity' => 'Activity',
        ],

        'status' => [
            'no_active_lease' => 'No Active Lease',
            'in_arrears' => 'In Arrears',
            'up_to_date' => 'Up to Date',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],

        'contact_info' => [
            'title' => 'Contact Information',
            'email' => 'Email',
            'phone' => 'Phone',
            'id_number' => 'ID Number',
            'tenant_since' => 'Tenant Since',
        ],

        'stats' => [
            'unit' => 'Unit',
            'monthly_rent' => 'Monthly Rent',
            'deposit' => 'Deposit',
            'arrears' => 'Arrears',
            'credit_balance' => 'Credit Balance',
            'adjust' => 'Adjust',
        ],

        'primary_contact' => [
            'title' => 'Primary Emergency Contact',
            'none' => 'No primary contact set',
        ],

        'lease' => [
            'current_title' => 'Current Lease',
            'property_building_unit' => 'Property / Building / Unit',
            'property_fallback' => 'Property',
            'building_fallback' => 'Building',
            'unit_prefix' => 'Unit',
            'lease_period' => 'Lease Period',
            'ongoing' => 'Ongoing',
            'monthly_rent' => 'Monthly Rent',
            'deposit_paid' => 'Deposit Paid',
            'service_charge' => 'Service Charge',
            'status_label' => 'Status',
            'rent_history' => 'Rent History',
            'no_active_title' => 'No Active Lease',
            'no_active_body' => 'This tenant does not have an active lease.',
            'past_leases' => 'Past Leases',
            'per_month_suffix' => '/mo',
        ],

        'payments' => [
            'recent_invoices' => 'Recent Invoices',
            'invoice_number' => 'Invoice #',
            'date' => 'Date',
            'amount' => 'Amount',
            'status' => 'Status',
            'no_invoices' => 'No invoices found',
            'recent_payments' => 'Recent Payments',
            'no_payments' => 'No payments recorded',
        ],

        'documents' => [
            'title' => 'Documents',
            'files_count' => '{count} files',
            'type_fallback' => 'Other',
            'view' => 'View',
            'download' => 'Download',
            'none' => 'No documents uploaded',
        ],

        'notes' => [
            'title' => 'Private Notes',
            'add' => 'Add Note',
            'author_unknown' => 'Unknown',
            'edit_aria' => 'Edit note',
            'delete_aria' => 'Delete note',
            'none' => 'No notes yet. Add your first note about this tenant.',
        ],

        'contacts' => [
            'title' => 'Emergency Contacts',
            'add' => 'Add Contact',
            'primary_badge' => 'Primary',
            'edit_aria' => 'Edit emergency contact',
            'delete_aria' => 'Delete emergency contact',
            'none' => 'No emergency contacts. Add one for this tenant.',
        ],

        'activity' => [
            'title' => 'Activity Timeline',
            'by' => 'by {name}',
            'system' => 'System',
            'none' => 'No activity recorded yet.',
        ],

        'edit_modal' => [
            'title' => 'Edit Tenant Profile',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'id_number' => 'ID Number',
            'dob' => 'Date of Birth',
            'dob_hint' => '(optional — required for minor consent flow)',
            'minor_title' => 'Minor — Parental Consent Required',
            'minor_body' => 'Kenya DPA Article 8 / Section 33 requires verifiable parental consent before processing data for tenants under 18.',
            'consent_url' => 'Parental Consent Artefact URL',
            'consent_url_placeholder' => 'https://drive.example.com/consent.pdf',
            'consent_at' => 'Consent Provided At',
            'consent_required_note' => 'Both artefact URL and timestamp must be provided before save.',
            'cancel' => 'Cancel',
            'save' => 'Save Changes',
        ],

        'note_modal' => [
            'edit_title' => 'Edit Note',
            'add_title' => 'Add Note',
            'label' => 'Note',
            'placeholder' => 'Write your note here...',
            'pin' => 'Pin this note',
            'cancel' => 'Cancel',
            'save' => 'Save',
            'add' => 'Add Note',
        ],

        'contact_modal' => [
            'edit_title' => 'Edit Contact',
            'add_title' => 'Add Emergency Contact',
            'name' => 'Name',
            'name_placeholder' => 'John Doe',
            'relationship' => 'Relationship',
            'relationship_placeholder' => 'Spouse, Parent, Sibling, etc.',
            'phone' => 'Phone',
            'phone_placeholder' => '+254 712 345 678',
            'email' => 'Email (Optional)',
            'email_placeholder' => 'contact@example.com',
            'set_primary' => 'Set as primary contact',
            'cancel' => 'Cancel',
            'save' => 'Save',
            'add' => 'Add Contact',
        ],

        'wallet_modal' => [
            'title' => 'Adjust Wallet Balance',
            'current_balance' => 'Current Balance',
            'adjustment_type' => 'Adjustment Type',
            'credit' => '+ Credit (Add)',
            'debit' => '− Debit (Remove)',
            'amount' => 'Amount ({currency})',
            'amount_placeholder' => 'Enter amount',
            'reason' => 'Reason',
            'reason_placeholder' => 'e.g., Refund for overcharge, Goodwill credit',
            'warning_label' => 'Warning:',
            'warning_body' => 'Debit amount exceeds current balance. This will result in a negative balance.',
            'cancel' => 'Cancel',
            'add_credit' => 'Add Credit',
            'remove_credit' => 'Remove Credit',
        ],

        'confirm' => [
            'delete_note' => 'Delete this note?',
            'delete_contact' => 'Delete this emergency contact?',
        ],
    ],

    'index' => [
        'head_title' => 'Tenants',
        'heading' => 'Tenants',
        'subtitle' => 'Manage your tenants and invitations',
        'invite_tenant' => 'Invite Tenant',
        'view' => 'View',
        'pending' => 'Pending',
        'viewed' => 'Viewed',
        'per_month' => '/mo',
        'no_unit_assigned' => 'No unit assigned',
        'unit_prefix' => 'Unit {number}',
        'unit_label' => 'Unit',
        'deposit_label' => 'Deposit:',
        'start_label' => 'Start:',
        'expires_label' => 'Expires:',

        'tabs' => [
            'active' => 'Active Tenants',
            'pending' => 'Pending Invitations',
            'past' => 'Past Tenants',
        ],

        'stats' => [
            'active_tenants' => 'Active Tenants',
            'pending_invites' => 'Pending Invites',
            'monthly_rent' => 'Monthly Rent',
            'total_arrears' => 'Total Arrears',
        ],

        'search' => [
            'placeholder' => 'Search tenants...',
            'pending_placeholder' => 'Search by name, email, or phone...',
        ],

        'table' => [
            'tenant' => 'Tenant',
            'contact' => 'Contact',
            'unit' => 'Unit',
            'payment' => 'Payment',
            'rent' => 'Rent',
            'actions' => 'Actions',
            'tenant_info' => 'Tenant Info',
            'lease_terms' => 'Lease Terms',
            'status' => 'Status',
            'last_unit' => 'Last Unit',
            'end_date' => 'End Date',
        ],

        'lease_status' => [
            'no_lease' => 'No Lease',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],

        'payment_status' => [
            'na' => 'N/A',
            'arrears' => 'Arrears',
            'up_to_date' => 'Up to date',
        ],

        'empty_active' => [
            'title' => 'No active tenants',
            'description' => 'Invite tenants to get started.',
            'search' => 'Try a different search term.',
        ],

        'empty_pending' => [
            'title' => 'No pending invitations',
            'description' => 'All invitations have been accepted or expired.',
            'search' => 'Try a different search term.',
        ],

        'empty_past' => [
            'title' => 'No past tenants',
            'description' => 'Past tenants will appear here after their lease ends.',
            'search' => 'Try a different search term.',
        ],

        'pagination' => [
            'page_of' => 'Page {current} of {total}',
            'previous' => 'Previous',
            'next' => 'Next',
        ],

        'actions' => [
            'copy' => 'Copy link',
            'resend' => 'Resend',
            'edit' => 'Edit',
            'cancel' => 'Cancel',
        ],

        'confirm' => [
            'resend' => 'Resend this invitation?',
            'cancel' => 'Are you sure you want to cancel this invitation?',
        ],

        'alert' => [
            'copied' => 'Invitation link copied!',
        ],
    ],

    'history' => [
        'head_title' => 'Tenant History',
        'heading' => 'Tenant History',
        'subtitle' => 'View past tenants who have moved out',
        'total_past_tenants' => 'Total Past Tenants',
        'search_label' => 'Search',
        'search_placeholder' => 'Search by name or email...',
        'building_label' => 'Building',
        'all_buildings' => 'All Buildings',
        'clear' => 'Clear',
        'na' => 'N/A',
        'duration_months' => '{count} months',
        'not_specified' => 'Not specified',
        'view_profile' => 'View Profile',

        'table' => [
            'tenant' => 'Tenant',
            'last_unit' => 'Last Unit',
            'lease_period' => 'Lease Period',
            'duration' => 'Duration',
            'move_out_reason' => 'Move-out Reason',
            'actions' => 'Actions',
        ],

        'empty' => [
            'title' => 'No past tenants found',
            'description' => 'Past tenant records will appear here after move-outs',
        ],
    ],
];
