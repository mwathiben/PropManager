<?php

declare(strict_types=1);

return [
    'mrr' => [
        'heading' => 'MRR — monthly recurring revenue',
        'total_label' => 'Total MRR',
        'by_plan_label' => 'MRR by plan',
        'waterfall_new' => 'New',
        'waterfall_expansion' => 'Expansion',
        'waterfall_contraction' => 'Contraction',
        'waterfall_churned' => 'Churned',
    ],
    'churn' => [
        'cohort_heading' => 'Subscription cohort retention',
        'retention_label' => 'Retention',
        'monthly_rate_label' => 'Monthly churn rate',
    ],
    'referral' => [
        'your_code_heading' => 'Your referral code',
        'your_referrals_heading' => 'Your referrals',
        'redeem_form_heading' => 'Redeem a referral code',
        'status_pending' => 'Pending',
        'status_attributed' => 'Attributed',
        'status_rewarded' => 'Rewarded',
    ],
    'engagement' => [
        'score_label' => 'Engagement score',
        'score_explanation' => 'Composite of login recency, milestone progress, usage, property growth, tenant activity.',
        'low_engagement_warning' => 'Your engagement score is low — let us help you get more out of the platform.',
    ],
    'lifecycle' => [
        'trial_ending_subject' => 'Your trial ends in :days day(s)',
        'trial_ending_heading' => 'Your trial is ending soon',
        'trial_ending_body' => 'You have :days day(s) left to choose a plan. Upgrade now to keep all your data and continue without interruption.',
        'upgrade_cta' => 'Choose a plan',
        'dunning_subject' => 'We could not process your payment (day :days)',
        'dunning_heading' => 'Payment issue',
        'dunning_body' => 'It has been :days day(s) since we could not process your payment. Please update your card to avoid losing access.',
        'update_card_cta' => 'Update payment method',
        'winback_subject' => 'We would love to have you back',
        'winback_heading' => 'Come back with a discount',
        'winback_body' => 'Use code :code at checkout for a discounted return to the platform.',
        'see_plans_cta' => 'See plans',
        'activation_nudge_subject' => 'Pick up where you left off',
        'activation_nudge_heading' => 'Resume your setup',
        'activation_nudge_body' => 'Your onboarding paused a few days ago. We are here to help you finish it in minutes.',
        'resume_cta' => 'Resume onboarding',
        'signature' => 'Thanks, the :app team',
    ],
];
