<?php

declare(strict_types=1);

return [
    'cohort' => [
        // A cohort smaller than this is flagged insufficient_sample so the
        // ops UI can mute it — retention percentages over a handful of
        // users are noise, not signal.
        'min_sample' => env('GROWTH_COHORT_MIN_SAMPLE', 20),
    ],
];
