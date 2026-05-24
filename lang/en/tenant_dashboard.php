<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant home dashboard page. Mirror en/sw/ar.
 */
return [
    'title' => 'My Dashboard',
    'welcome' => 'Welcome to {building}',
    'unit_floor' => 'Unit {unit} • Floor {floor}',
    'pay_now' => 'Pay Now',
    'no_lease' => [
        'pending_title' => 'You Have Pending Invitations',
        'pending_subtitle' => 'A landlord has invited you to lease a property. Review the details below and accept to get started.',
        'no_active_lease' => 'No Active Lease',
    ],
    'invitation' => [
        'monthly_rent' => 'Monthly Rent',
        'security_deposit' => 'Security Deposit',
        'start_date' => 'Start Date',
        'floor' => 'Floor',
        'service_charge' => 'Service Charge',
        'per_month' => '/month',
        'total_move_in' => 'Total Move-in Cost',
        'landlord' => 'Landlord',
        'expires' => 'Expires {date}',
        'processing' => 'Processing...',
        'accept' => 'Accept Invitation',
        'unit_label' => '{building} • Unit {unit}',
    ],
    'confirm' => [
        'decline' => 'Are you sure you want to decline this invitation? This action cannot be undone.',
    ],
    'balance' => [
        'current' => 'Current Balance',
        'credit' => 'Credit Balance',
        'arrears' => 'Outstanding Arrears',
    ],
    'action_items' => [
        'overdue_invoices' => 'Overdue Invoices',
        'days_late' => '{days} days late',
        'pending_invoices' => 'Pending Invoices',
        'awaiting_payment' => 'Awaiting payment',
        'view' => 'View',
        'all_paid' => 'All Paid',
        'no_pending_invoices' => 'No pending invoices',
        'open_tickets' => 'Open Tickets',
        'issues_being_resolved' => 'Issues being resolved',
        'no_issues' => 'No Issues',
        'all_tickets_resolved' => 'All tickets resolved',
        'monthly_rent' => 'Monthly Rent',
        'due_monthly' => 'Due monthly',
    ],
    'next_payment' => [
        'title' => 'Next Payment Due',
        'pay_invoice' => 'Pay Invoice',
        'view_details' => 'View Details',
    ],
    'tickets' => [
        'title' => 'My Tickets',
        'view_all' => 'View All',
        'none' => 'No active tickets',
        'all_good' => 'Everything looks good!',
        'report_issue' => 'Report an Issue',
    ],
    'payments' => [
        'title' => 'Payment History',
        'view_all' => 'View All',
        'none' => 'No payment history',
        'fallback_method' => 'Payment',
    ],
    'lease' => [
        'title' => 'Lease Information',
        'view_details' => 'View Details',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'open_ended' => 'Open-ended',
        'monthly_rent' => 'Monthly Rent',
        'deposit_paid' => 'Deposit Paid',
    ],
    'caretaker' => [
        'title' => 'Building Caretaker',
        'whatsapp' => 'WhatsApp',
        'none' => 'No caretaker assigned',
    ],
];
