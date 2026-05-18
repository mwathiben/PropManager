<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
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

    /**
     * Phase-59 SIGNED-URLS-1: return a short-lived URL the browser can
     * fetch directly without streaming through PHP-FPM. On s3 this is
     * a native presigned URL; on the local driver
     * Filesystem::temporaryUrl() throws, so we fall back to a Laravel
     * signed route that re-validates ownership before streaming.
     *
     * The public API is identical across drivers — callers don't need
     * to know which one is active.
     */
    public function temporaryUrl(
        string $path,
        ?int $landlordId = null,
        int $expiresMinutes = 5,
        ?string $filename = null,
        string $disposition = 'attachment',
    ): string {
        $disk = $this->resolve($landlordId);
        $expires = now()->addMinutes($expiresMinutes);

        try {
            return $disk->temporaryUrl($path, $expires);
        } catch (RuntimeException $e) {
            return URL::temporarySignedRoute(
                'files.local-stream',
                $expires,
                array_filter([
                    'path' => $path,
                    'filename' => $filename,
                    'disposition' => $disposition,
                ]),
            );
        }
    }
}
