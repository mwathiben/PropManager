<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase-59 PATH-CAVEAT-1: an absolute filesystem path scoped to a
 * try/finally for subprocess callers. On the local driver the
 * handle wraps the existing Storage::tenant()->path() return; on s3
 * (where ->path() throws) the handle downloads the file to a UUID-
 * named temp dir.
 *
 * Implements __destruct() as a safety net for forgotten cleanup —
 * but callers SHOULD still wrap in try/finally so cleanup happens
 * deterministically rather than at GC time.
 */
class TempFileHandle
{
    private bool $cleaned = false;

    /**
     * @param  bool  $owned  whether $this owns $absolutePath and must unlink it on cleanup
     */
    public function __construct(
        private readonly string $absolutePath,
        private readonly bool $owned,
    ) {}

    public function path(): string
    {
        return $this->absolutePath;
    }

    public function cleanup(): void
    {
        if ($this->cleaned || ! $this->owned) {
            $this->cleaned = true;

            return;
        }

        try {
            $this->unlinkFileIfExists();
            $this->rmdirIfTempDir();
        } catch (Throwable $e) {
            Log::warning('temp_file_handle_cleanup_failed', [
                'path' => $this->absolutePath,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleaned = true;
        }
    }

    private function unlinkFileIfExists(): void
    {
        if (is_file($this->absolutePath)) {
            @unlink($this->absolutePath);
        }
    }

    private function rmdirIfTempDir(): void
    {
        $dir = dirname($this->absolutePath);
        if (is_dir($dir) && str_contains($dir, sys_get_temp_dir())) {
            @rmdir($dir);
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
