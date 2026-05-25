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
    'plans' => [
        'title' => 'Choose Your Plan',
        'subtitle' => 'Select the plan that best fits your needs',
        'back' => 'Back to Subscription',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'save_up_to' => 'Save up to 17%',
        'popular_badge' => 'POPULAR',
        'current_badge' => 'CURRENT PLAN',
        'year' => 'year',
        'save_amount' => 'Save {amount}',
        'processing' => 'Processing...',
        'current_plan' => 'Current Plan',
        'downgrade' => 'Downgrade',
        'upgrade' => 'Upgrade',
        'subscribe_failed' => 'Failed to process subscription. Please try again.',
        'faq' => [
            'heading' => 'Frequently Asked Questions',
            'change_q' => 'Can I change my plan later?',
            'change_a' => "Yes! You can upgrade or downgrade your plan at any time. When upgrading, you'll be charged the difference immediately. When downgrading, changes take effect at your next billing date.",
            'limits_q' => 'What happens when I reach my limits?',
            'limits_a' => "You won't be able to create new items beyond your plan's limits. Your existing data remains accessible, and you can upgrade at any time to increase your limits.",
            'trial_q' => 'Is there a trial period?',
            'trial_a' => "Yes! All paid plans come with a 14-day free trial. You won't be charged until the trial ends, and you can cancel anytime.",
        ],
    ],
];
