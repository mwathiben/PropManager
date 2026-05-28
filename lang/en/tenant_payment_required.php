<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant initial-payment gating page (deposit + first-month + other charges
 * verification). Strings cover status banner, unit/breakdown cards, pay-online and upload-proof flows,
 * file-validation errors and the help footer. Mirror en/sw/ar with identical key sets.
 */
return [
    'page_title' => 'Payment Required',
    'header_title' => 'Initial Payment Required',
    'header_subtitle' => 'Complete your payment to access the tenant portal',
    'status' => [
        'pending_payment_title' => 'Payment Required',
        'pending_payment_message' => 'Please upload proof of payment or pay online to continue.',
        'verification_pending_title' => 'Verification Pending',
        'verification_pending_message' => 'Your payment proof has been submitted and is awaiting verification by your landlord.',
        'rejected_title' => 'Verification Rejected',
        'rejected_default_message' => 'Your payment proof was rejected. Please resubmit.',
        'verified_title' => 'Payment Verified',
        'verified_message' => 'Your payment has been verified.',
    ],
    'unit_card' => [
        'heading' => 'Your Unit',
        'building_label' => 'Building',
        'unit_label' => 'Unit',
    ],
    'breakdown' => [
        'heading' => 'Payment Required',
        'security_deposit' => 'Security Deposit',
        'first_month_rent' => 'First Month Rent',
        'other_charges_default' => 'Other Charges',
        'total_required' => 'Total Required',
        'amount_paid' => 'Amount Paid',
        'balance_due' => 'Balance Due',
    ],
    'pay_online' => [
        'heading' => 'Pay Online',
        'description' => 'Pay securely with your card or mobile money. Your payment will be verified automatically.',
        'cta' => 'Pay {amount} Now',
    ],
    'divider_or_upload' => 'or upload proof of payment',
    'upload' => [
        'heading' => 'Upload Payment Proof',
        'description' => "If you've already made a bank transfer or mobile money payment, upload your proof here.",
        'click_to_upload' => 'Click to upload',
        'click_to_upload_suffix' => 'or drag and drop',
        'file_constraints' => 'PDF, JPG, PNG up to 10MB each',
        'submit_idle' => 'Submit for Verification',
        'submit_processing' => 'Uploading...',
        'errors' => [
            'invalid_type' => 'Only PDF, JPG, and PNG files are allowed.',
            'too_large' => 'Each file must not exceed 10MB.',
        ],
    ],
    'submitted' => [
        'heading' => 'Submitted Documents',
    ],
    'help' => [
        'heading' => 'Need help?',
        'body' => 'If you have questions about your payment or need assistance, please contact your property manager.',
    ],
];
