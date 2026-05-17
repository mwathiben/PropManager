<?php

declare(strict_types=1);

return [
    'rent_reminder' => [
        'subject' => '[TODO-ar] Rent reminder for invoice :number',
        'body_before' => '[TODO-ar] Your rent payment of KES :amount on invoice :number is due in :days day(s).',
        'body_due_today' => '[TODO-ar] Your rent payment of KES :amount on invoice :number is due today.',
        'body_after' => '[TODO-ar] Your rent payment of KES :amount on invoice :number is :days day(s) overdue. Please pay as soon as possible to avoid late fees.',
    ],
    'vacancy' => [
        'task_title' => '[TODO-ar] List unit :number for a new tenant',
        'task_description' => '[TODO-ar] Unit :number is now vacant. Update the listing, photos, and screening criteria.',
    ],
    'occupancy' => [
        'breach_subject' => '[TODO-ar] Occupancy below target — :name',
        'breach_body' => '[TODO-ar] Building :name is at :current% occupancy, below the target of :target%. Consider reviewing pricing or marketing.',
    ],
    'late_fee' => [
        'sms_subject' => '[TODO-ar] Urgent: invoice :number overdue',
        'sms_body' => '[TODO-ar] Invoice :number with balance KES :amount is overdue. Please pay today to avoid further action.',
        'task_title' => '[TODO-ar] Call :tenant about overdue invoice :number',
        'task_description' => '[TODO-ar] Tenant has not paid invoice :number despite reminders. Place a call to confirm payment intent.',
        'eviction_draft_body' => '[TODO-ar] NOTICE TO TENANT :tenant

The rent under invoice :number (due :due_date) remains unpaid for more than 30 days. The total amount currently owed is KES :amount.

Unless payment is received within seven (7) days from the date of this notice, the landlord may commence proceedings under the relevant Kenyan tenancy law for recovery of the premises and outstanding arrears.

This is a DRAFT pending landlord review.',
    ],
    'lease_renewal' => [
        'subject' => '[TODO-ar] Lease renewal — :days day(s) remaining',
        'body' => '[TODO-ar] Your lease ends on :end_date — :days day(s) from today. Please review the renewal terms.',
        'proposed' => '[TODO-ar] Renewal terms proposed. The tenant will be notified.',
        'confirmed' => '[TODO-ar] Renewal confirmed. Lease end date and rent updated.',
        'tenant_accepted' => '[TODO-ar] You accepted the proposed renewal. Awaiting landlord confirmation.',
        'tenant_rejected' => '[TODO-ar] You rejected the proposed renewal. The landlord will be notified.',
        'tenant_countered' => '[TODO-ar] Your counter-offer has been sent to your landlord for review.',
        'counter_accepted' => '[TODO-ar] You accepted the tenant counter-offer. The renewal is now in accepted status.',
        'counter_rejected' => '[TODO-ar] You rejected the tenant counter-offer. The renewal is now closed.',
        'counter_re_proposed' => '[TODO-ar] You re-proposed the renewal with new terms. The tenant will be notified.',
    ],
    'payment_plan' => [
        'approved' => '[TODO-ar] Payment plan approved. The tenant has been notified.',
        'rejected' => '[TODO-ar] Payment plan rejected. The tenant has been notified.',
        'approved_subject' => '[TODO-ar] Your payment plan was approved',
        'approved_body' => '[TODO-ar] Your landlord approved your payment plan request. Installment due dates and amounts are now in effect.',
        'rejected_subject' => '[TODO-ar] Your payment plan was rejected',
        'rejected_body' => '[TODO-ar] Your landlord rejected the payment plan request. Reason: :reason',
    ],
    'deposit_refund' => [
        'approved' => '[TODO-ar] Deposit refund approved. The tenant has been notified.',
        'rejected' => '[TODO-ar] Deposit refund rejected. The tenant has been notified.',
        'paid' => '[TODO-ar] Deposit refund marked paid. The tenant has been notified.',
        'approved_subject' => '[TODO-ar] Your deposit refund was approved',
        'approved_body' => '[TODO-ar] Your deposit refund of KES :amount has been approved and will be processed shortly.',
        'rejected_subject' => '[TODO-ar] Your deposit refund was rejected',
        'rejected_body' => '[TODO-ar] Your deposit refund request was rejected. Reason: :reason',
        'paid_subject' => '[TODO-ar] Your deposit refund has been paid',
        'paid_body' => '[TODO-ar] Your deposit refund has been paid. Payment reference: :reference',
        'b2c_queued' => '[TODO-ar] Refund queued for M-Pesa B2C payout.',
        'b2c_sent' => '[TODO-ar] M-Pesa B2C payout initiated. Awaiting confirmation.',
        'b2c_succeeded' => '[TODO-ar] M-Pesa B2C payout succeeded.',
        'b2c_failed' => '[TODO-ar] M-Pesa B2C payout failed.',
        'b2c_timed_out' => '[TODO-ar] M-Pesa B2C payout timed out — reconciliation pending.',
    ],
];
