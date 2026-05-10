<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Failure Email Recipient
    |--------------------------------------------------------------------------
    |
    | OBS-3: when a scheduled command fails (non-zero exit), Laravel emails
    | the captured stdout/stderr to this address. Leave blank in dev / CI to
    | suppress mail; set in production so ops gets paged when invoice
    | generation, late-fee runs, or reconciliation jobs fail silently.
    |
    */
    'failure_email' => env('SCHEDULE_FAILURE_EMAIL'),
];
