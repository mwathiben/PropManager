<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Disk (Phase-58 SHARED-DISK-MIGRATION)
    |--------------------------------------------------------------------------
    |
    | The disk used for tenant-scoped file storage (Document, WaterReading
    | photo, Lease docs, Invoice PDFs, TenantKyc docs). Defaults to 'local'
    | so dev/test environments keep working without S3 setup.
    |
    | Production operators flip this to 's3' (or another driver) via the
    | FILESYSTEM_TENANT_DISK env var. The 28 (now zero) hardcoded
    | Storage::disk('local') callsites were refactored in Phase 58 to flow
    | through Storage::tenant() → TenantDiskResolver → this config knob.
    | No DB migration is required because the path strings stored in
    | Document.file_path / WaterReading.photo_path / Lease.lease_doc_path /
    | Invoice.pdf_path stay the same — only the disk used to access them
    | changes.
    |
    */

    'tenant_disk' => env('FILESYSTEM_TENANT_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // UPLOAD-5: TenantPaymentVerificationController and other paths
        // store payment proofs / KYC docs to a 'private' disk that did
        // not previously exist in this config — Laravel was throwing
        // InvalidArgumentException at runtime on every upload. The disk
        // points at the same root as 'local' (storage/app/private) so
        // existing code paths continue to work; the alias makes the
        // intent explicit at the call site ('private' reads better than
        // 'local' for sensitive uploads).
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Phase-37 PWA-RETENTION-STATS-2: cold storage for
        // product:cold-storage-rollover. Local in dev; in prod
        // operators point ARCHIVE_DISK at an S3 bucket with a
        // LIFECYCLE rule transitioning >365 day objects to
        // Glacier Deep Archive. Defaults to local so dev/test
        // environments don't fail when the cron runs.
        'archive' => [
            'driver' => env('ARCHIVE_DISK_DRIVER', 'local'),
            'root' => storage_path('app/archive'),
            'bucket' => env('ARCHIVE_BUCKET'),
            'region' => env('AWS_DEFAULT_REGION'),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
