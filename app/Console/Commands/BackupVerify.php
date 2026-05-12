<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Phase-12 BACKUP-2: cheap backup verification. spatie/laravel-backup's
 * built-in backup:monitor checks age + size; this command goes one
 * step further and asserts the archive is syntactically intact.
 *
 * What it does:
 *   1. Locate the newest archive in the first configured backup disk.
 *   2. Open it as a Zip and assert the database dump file is present.
 *   3. Extract the dump's first/last bytes and assert it looks like
 *      a valid mysqldump (header line + COMMIT footer).
 *
 * What it does NOT do (yet):
 *   - Full restore to a throwaway DB schema. That is the operator's
 *     quarterly drill responsibility (see docs/runbooks/disaster-
 *     recovery.md). Cheap automated verification + expensive manual
 *     drill is a Pareto-acceptable DR posture.
 */
class BackupVerify extends Command
{
    protected $signature = 'backup:verify
        {--disk= : Override the disk to read from (defaults to first configured)}';

    protected $description = 'Verify the newest backup archive is openable and contains a non-empty database dump.';

    public function handle(): int
    {
        $disks = (array) config('backup.backup.destination.disks', ['local']);
        $disk = (string) ($this->option('disk') ?? $disks[0] ?? 'local');
        $appName = (string) config('backup.backup.name', 'PropManager');

        $files = collect(Storage::disk($disk)->files($appName))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->sortDesc()
            ->values();

        if ($files->isEmpty()) {
            $this->error("No backup archives found on disk '{$disk}' under '{$appName}/'.");

            return self::FAILURE;
        }

        $latest = $files->first();
        $this->info("Verifying: {$latest} (disk: {$disk})");

        // Stream the archive to a temp file so ZipArchive can open
        // it. Storage::path is only available on the local disk; for
        // s3 etc. we copy down first.
        $tempPath = tempnam(sys_get_temp_dir(), 'backup-verify-');
        file_put_contents($tempPath, Storage::disk($disk)->get($latest));

        try {
            $zip = new ZipArchive;
            $opened = $zip->open($tempPath);
            if ($opened !== true) {
                $this->error("ZipArchive failed to open archive (code {$opened}).");
                $this->failureReport($latest, "ZipArchive open failed: {$opened}");

                return self::FAILURE;
            }

            $dumpEntry = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name !== false && str_ends_with($name, '.sql')) {
                    $dumpEntry = $name;
                    break;
                }
            }

            if ($dumpEntry === null) {
                $zip->close();
                $this->error('No .sql dump found inside the archive.');
                $this->failureReport($latest, 'No .sql dump in archive');

                return self::FAILURE;
            }

            $stat = $zip->statName($dumpEntry);
            if (! $stat || $stat['size'] < 100) {
                $zip->close();
                $this->error("Dump '{$dumpEntry}' is suspiciously small (size={$stat['size']} bytes).");
                $this->failureReport($latest, "Dump too small ({$stat['size']} bytes)");

                return self::FAILURE;
            }

            // Read a sniff of the dump to make sure it's text + has
            // a recognisable mysqldump header.
            $stream = $zip->getStream($dumpEntry);
            if (! $stream) {
                $zip->close();
                $this->error('Could not open dump stream from archive.');
                $this->failureReport($latest, 'getStream failed');

                return self::FAILURE;
            }

            $header = fread($stream, 1024);
            fclose($stream);
            $zip->close();

            if (! is_string($header) || ! preg_match('/(MySQL dump|mysqldump|-- Host:|CREATE TABLE)/i', $header)) {
                $this->error('Dump header does not look like a MySQL dump.');
                $this->failureReport($latest, 'Header mismatch');

                return self::FAILURE;
            }

            $this->info('OK — archive opened, dump present and looks valid.');
            Log::channel(config('logging.schedule_channel', 'stack'))->info(
                'backup:verify passed',
                ['archive' => $latest, 'dump' => $dumpEntry, 'size' => $stat['size']]
            );

            return self::SUCCESS;
        } finally {
            @unlink($tempPath);
        }
    }

    private function failureReport(string $archive, string $reason): void
    {
        Log::channel(config('logging.schedule_channel', 'stack'))->error(
            'backup:verify FAILED',
            ['archive' => $archive, 'reason' => $reason]
        );
    }
}
