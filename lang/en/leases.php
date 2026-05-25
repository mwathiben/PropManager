<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: lease-creation (tenant invitation) page. Mirror en/sw/ar.
 */
return [
    'index' => [
        'title' => 'Lease Agreements',
        'subtitle' => 'View and manage all lease agreements',
        'stats' => [
            'total' => 'Total Leases',
            'active' => 'Active Leases',
            'terminated' => 'Terminated Leases',
        ],
        'filters' => [
            'search' => 'Search',
            'search_placeholder' => 'Search by tenant name or unit...',
            'status' => 'Status',
            'all_statuses' => 'All Statuses',
            'building' => 'Building',
            'all_buildings' => 'All Buildings',
            'clear' => 'Clear',
        ],
        'status' => [
            'active' => 'Active',
            'terminated' => 'Terminated',
        ],
        'table' => [
            'tenant' => 'Tenant',
            'unit' => 'Unit',
            'start_date' => 'Start Date',
            'rent' => 'Rent',
            'status' => 'Status',
            'documents' => 'Documents',
            'actions' => 'Actions',
        ],
        'na' => 'N/A',
        'duration' => [
            'months' => '{count} month | {count} months',
            'less_than_month' => 'Less than a month',
        ],
        'documents' => [
            'count' => '{count} doc | {count} docs',
            'none' => 'No documents',
        ],
        'view_tenant' => 'View Tenant',
        'empty' => [
            'title' => 'No lease agreements found',
            'description' => 'Lease agreements will appear here when tenants are added',
        ],
    ],
    'create' => [
        'title' => 'Invite Tenant',
        'heading' => 'Invite Tenant: Unit {unit}',
        'subheading' => 'Send a lease invitation for Floor {floor}',
        'success' => [
            'title' => 'Invitation Sent!',
            'sent_to' => 'An invitation has been sent to',
            'via' => 'via {channels}.',
            'follow_up' => 'The tenant will receive a notification with a link to review the lease terms and create their account.',
            'send_another' => 'Send Another Invitation',
            'return_dashboard' => 'Return to Dashboard',
        ],
        'how_it_works' => [
            'title' => 'How it works:',
            'step1' => "Enter the tenant's email and lease terms below",
            'step2' => 'Tenant receives an email with a link to review and accept',
            'step3' => 'Tenant creates their account and the lease is activated',
        ],
        'tenant_info' => [
            'title' => 'Tenant Information',
            'subtitle' => "Enter the prospective tenant's contact details",
        ],
        'fields' => [
            'email' => 'Email Address',
            'email_placeholder' => 'tenant@example.com',
            'email_help' => 'The invitation will be sent to this email',
            'name' => 'Full Name (Optional)',
            'name_placeholder' => 'John Doe',
            'name_help' => 'Tenant can update this when accepting',
            'phone' => 'Phone Number',
            'phone_optional' => '(Optional)',
            'phone_placeholder' => '+254 7XX XXX XXX',
            'phone_required_help' => 'Required for SMS/WhatsApp delivery',
            'monthly_rent' => 'Monthly Rent ({currency})',
            'service_charge' => 'Service Charge ({currency})',
            'service_charge_help' => 'Garbage, Security, Lights',
            'security_deposit' => 'Security Deposit ({currency})',
            'amount_placeholder' => '0.00',
            'start_date' => 'Lease Start Date',
            'end_date' => 'Lease End Date (Optional)',
            'end_date_help' => 'Leave empty for month-to-month lease',
        ],
        'lease_terms' => [
            'title' => 'Lease Terms',
            'subtitle' => 'Set the rent and deposit amounts for this lease',
        ],
        'totals' => [
            'move_in' => 'Total Due for Move-In:',
        ],
        'lease_period' => [
            'title' => 'Lease Period',
        ],
        'channels' => [
            'title' => 'Send Invitation Via',
            'subtitle' => 'Choose how to notify the tenant about this invitation',
            'email' => 'Email',
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            'not_configured' => 'Not configured - set up in Settings',
            'enter_phone' => 'Enter phone number above',
            'cost_warning' => 'SMS and WhatsApp messages may incur charges based on your provider settings.',
        ],
        'required' => '*',
        'cancel' => 'Cancel',
        'sending' => 'Sending...',
        'send' => 'Send Invitation',
    ],
];
