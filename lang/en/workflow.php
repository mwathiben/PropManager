<?php

declare(strict_types=1);

/*
 * Phase-29 [WORKFLOW-AUTOMATION] i18n keys for landlord-driven
 * workflow notifications. Swahili parity enforced by Phase24CiTest.
 */

return [
    'rent_reminder' => [
        'subject' => 'Rent reminder for invoice :number',
        'body_before' => 'Your rent payment of KES :amount on invoice :number is due in :days day(s).',
        'body_due_today' => 'Your rent payment of KES :amount on invoice :number is due today.',
        'body_after' => 'Your rent payment of KES :amount on invoice :number is :days day(s) overdue. Please pay as soon as possible to avoid late fees.',
    ],
    'vacancy' => [
        'task_title' => 'List unit :number for a new tenant',
        'task_description' => 'Unit :number is now vacant. Update the listing, photos, and screening criteria.',
    ],
    'occupancy' => [
        'breach_subject' => 'Occupancy below target — :name',
        'breach_body' => 'Building :name is at :current% occupancy, below the target of :target%. Consider reviewing pricing or marketing.',
    ],
    'late_fee' => [
        'sms_subject' => 'Urgent: invoice :number overdue',
        'sms_body' => 'Invoice :number with balance KES :amount is overdue. Please pay today to avoid further action.',
        'task_title' => 'Call :tenant about overdue invoice :number',
        'task_description' => 'Tenant has not paid invoice :number despite reminders. Place a call to confirm payment intent.',
        'eviction_draft_body' => "NOTICE TO TENANT :tenant\n\nThe rent under invoice :number (due :due_date) remains unpaid for more than 30 days. The total amount currently owed is KES :amount.\n\nUnless payment is received within seven (7) days from the date of this notice, the landlord may commence proceedings under the relevant Kenyan tenancy law for recovery of the premises and outstanding arrears.\n\nThis is a DRAFT pending landlord review.",
    ],
    'lease_renewal' => [
        'subject' => 'Lease renewal — :days day(s) remaining',
        'body' => 'Your lease ends on :end_date — :days day(s) from today. Please review the renewal terms.',
        'proposed' => 'Renewal terms proposed. The tenant will be notified.',
        'confirmed' => 'Renewal confirmed. Lease end date and rent updated.',
        'tenant_accepted' => 'You accepted the proposed renewal. Awaiting landlord confirmation.',
        'tenant_rejected' => 'You rejected the proposed renewal. The landlord will be notified.',
    ],
    'payment_plan' => [
        'approved' => 'Payment plan approved. The tenant has been notified.',
        'rejected' => 'Payment plan rejected. The tenant has been notified.',
        'approved_subject' => 'Your payment plan was approved',
        'approved_body' => 'Your landlord approved your payment plan request. Installment due dates and amounts are now in effect.',
        'rejected_subject' => 'Your payment plan was rejected',
        'rejected_body' => 'Your landlord rejected the payment plan request. Reason: :reason',
    ],
    'deposit_refund' => [
        'approved' => 'Deposit refund approved. The tenant has been notified.',
        'rejected' => 'Deposit refund rejected. The tenant has been notified.',
        'paid' => 'Deposit refund marked paid. The tenant has been notified.',
        'approved_subject' => 'Your deposit refund was approved',
        'approved_body' => 'Your deposit refund of KES :amount has been approved and will be processed shortly.',
        'rejected_subject' => 'Your deposit refund was rejected',
        'rejected_body' => 'Your deposit refund request was rejected. Reason: :reason',
        'paid_subject' => 'Your deposit refund has been paid',
        'paid_body' => 'Your deposit refund has been paid. Payment reference: :reference',
    ],
];
