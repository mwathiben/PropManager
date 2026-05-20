<?php

declare(strict_types=1);

return [
    'leaderboard' => [
        // Hard cap on how many ranked referrers any caller can request.
        'max' => env('REFERRAL_LEADERBOARD_MAX', 50),

        // A rewarded referral counts for this many attributed referrals
        // in the composite leaderboard score.
        'reward_weight' => env('REFERRAL_LEADERBOARD_REWARD_WEIGHT', 2),
    ],
];
