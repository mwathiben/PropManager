<?php

return [
    'bulk_max' => env('LEGAL_HOLD_BULK_MAX', 100),

    // Phase-68 STALE-SWEEP: a hold active longer than stale_after_days is
    // "stale" — the daily sweeper nudges the owning landlord to confirm or
    // release it, at most once per stale_reminder_cooldown_days.
    'stale_after_days' => (int) env('LEGAL_HOLD_STALE_AFTER_DAYS', 365),
    'stale_reminder_cooldown_days' => (int) env('LEGAL_HOLD_STALE_REMINDER_COOLDOWN_DAYS', 30),
];
