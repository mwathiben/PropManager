<?php

declare(strict_types=1);

namespace App\Services\Storage;

use RuntimeException;
use Throwable;

/**
 * Phase-59 PATH-CAVEAT-1: hands out TempFileHandle instances that
 * give subprocess callers a real local-disk path regardless of the
 * underlying tenant disk driver.
 *
 * Pattern:
 *   $handle = app(TempFileResolver::class)->for($relativePath);
 *   try {
 *       runSubprocess($handle->path());
 *   } finally {
 *       $handle->cleanup();
 *   }
 *
 * On local driver: handle wraps the existing absolute path; cleanup
 * is a no-op.
 * On s3/other: handle owns a tempfile materialised under
 * sys_get_temp_dir() that cleanup() unlinks.
 */
class TempFileResolver
{
    private const TEMP_DIR_PREFIX = 'pm-tenant-';

    public function __construct(
        private readonly TenantDiskResolver $diskResolver,
    ) {}

    public function for(string $relativePath, ?int $landlordId = null): TempFileHandle
    {
        $disk = $this->diskResolver->resolve($landlordId);

        try {
            $absolutePath = $disk->path($relativePath);

            return new TempFileHandle($absolutePath, owned: false);
        } catch (RuntimeException) {
            // Driver doesn't support path() (s3, etc). Download to a
            // temp dir and own the cleanup.
        } catch (Throwable) {
            // Fall through: any other failure to compute the path,
            // try the download fallback.
        }

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.self::TEMP_DIR_PREFIX.bin2hex(random_bytes(8));
        if (! @mkdir($tempDir, 0700, true) && ! is_dir($tempDir)) {
            throw new RuntimeException("Failed to create temp dir for {$relativePath}");
        }

        $tempFile = $tempDir.DIRECTORY_SEPARATOR.basename($relativePath);
        file_put_contents($tempFile, $disk->get($relativePath));

        return new TempFileHandle($tempFile, owned: true);
    }
}
