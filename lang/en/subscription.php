<?php

declare(strict_types=1);

/**
 * i18n migration: subscription / billing management page. Mirror en/sw/ar.
 */
return [
    'title' => 'Subscription',
    'subtitle' => 'Manage your plan and billing',
    'view_plans' => 'View Plans',
    'free' => 'Free',
    'plan_name' => '{name} Plan',
    'per_cycle' => 'per {cycle}',
    'your_plan' => 'Your Plan',
    'gateway_warning' => [
        'title' => 'Payment System Not Configured',
        'body' => 'The payment gateway is not yet configured. You can view plans and your current usage, but paid plan upgrades are temporarily unavailable. Please contact support if you need assistance.',
    ],
    'cycle' => [
        'month' => 'month',
    ],
    'details' => [
        'billing_cycle' => 'Billing Cycle',
        'ends_on' => 'Ends On',
        'next_billing' => 'Next Billing Date',
        'trial_ends' => 'Trial Ends',
        'na' => 'N/A',
    ],
    'actions' => [
        'resume' => 'Resume Subscription',
        'cancel' => 'Cancel Subscription',
        'upgrade' => 'Upgrade Plan',
        'change' => 'Change Plan',
    ],
    'usage' => [
        'heading' => 'Usage',
        'subtitle' => 'Your current usage against plan limits',
        'at_limit' => "You've reached your limit",
        'near_limit' => 'Approaching limit',
    ],
    'payments' => [
        'heading' => 'Payment History',
        'line' => '{plan} Payment',
        'default_plan' => 'Subscription',
        'download' => 'Download Receipt',
        'empty' => 'No payment history yet.',
    ],
    'cancel_modal' => [
        'title' => 'Cancel Subscription?',
        'intro' => 'Are you sure you want to cancel your subscription? You can choose to:',
        'at_period_end' => 'Cancel at period end',
        'keep_until' => 'Keep access until {date}',
        'immediately' => 'Cancel immediately',
        'immediately_note' => 'Lose access right away (no refund)',
        'keep' => 'Keep Subscription',
        'cancelling' => 'Cancelling...',
        'confirm' => 'Confirm Cancel',
    ],
];
