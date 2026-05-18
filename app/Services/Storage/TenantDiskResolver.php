<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Phase-58 TENANT-DISK-RESOLVER-1: single facade for tenant-aware file
 * storage.
 *
 * Pre-Phase-58 the codebase had 28 hardcoded local-disk callsites
 * (Storage facade pinned to the local driver) — a single-host
 * filesystem dependency that prevented horizontal scaling without
 * code changes. This resolver flows every read/write through
 * config('filesystems.tenant_disk', 'local') so an operator can flip
 * to S3 (or any other Filesystem driver) by setting
 * FILESYSTEM_TENANT_DISK=s3 in .env.
 *
 * The $landlordId parameter is recorded but unused today —
 * forward-compatible with per-tenant disk routing (different bucket
 * prefix per landlord, or even per-region S3 buckets) without
 * changing the public API.
 *
 * Fail-soft: if the configured disk name doesn't resolve to a valid
 * disk, falls back to the default disk + Log::warning.
 */
class TenantDiskResolver
{
    public const DEFAULT_DISK = 'local';

    public function resolve(?int $landlordId = null): Filesystem
    {
        $configured = (string) config('filesystems.tenant_disk', self::DEFAULT_DISK);

        try {
            return Storage::disk($configured);
        } catch (Throwable $e) {
            Log::warning('tenant_disk_resolve_fallback', [
                'configured' => $configured,
                'landlord_id' => $landlordId,
                'error' => $e->getMessage(),
            ]);

            return Storage::disk(self::DEFAULT_DISK);
        }
    }
}
