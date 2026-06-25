<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documenso self-hosted e-signature service
    |--------------------------------------------------------------------------
    |
    | Documenso (AGPL-3.0) runs as a SEPARATE self-hosted service that
    | PropManager calls over the network — it is the PKCS#12 certificate-backed
    | signed-PDF integrity layer for management agreements (Slice 2, PR 2.4b).
    | These are PLATFORM-level credentials shared across all tenants (like AWS
    | or mail), so they live in .env, NOT in the per-tenant payment_configurations
    | table.
    |
    | The owner signs through Documenso's embedded widget; on completion
    | Documenso fires the DOCUMENT_COMPLETED webhook to /api/webhooks/documenso,
    | which is the authoritative trigger for sealing the evidence + activating
    | the fee. The webhook carries a plain shared secret in the X-Documenso-Secret
    | header (Documenso emits no HMAC), compared in constant time.
    |
    | The create→upload→send flow targets API v1 (a self-hosted instance must run
    | with S3 upload transport, the standard production config); the signed-PDF
    | and certificate downloads use v2-beta (returns bytes, storage-agnostic).
    |
    */

    'base_url' => rtrim((string) env('DOCUMENSO_BASE_URL', ''), '/'),

    'api_token' => env('DOCUMENSO_API_TOKEN'),

    'webhook_secret' => env('DOCUMENSO_WEBHOOK_SECRET'),

    'api_version' => env('DOCUMENSO_API_VERSION', 'v1'),

    'timeout' => (int) env('DOCUMENSO_TIMEOUT', 30),

    'retry_attempts' => (int) env('DOCUMENSO_RETRY_ATTEMPTS', 3),

    'retry_delay_ms' => (int) env('DOCUMENSO_RETRY_DELAY_MS', 200),

    'storage_disk' => env('DOCUMENSO_STORAGE_DISK', 'private'),

];
