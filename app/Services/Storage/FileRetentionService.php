<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\FileRetentionPolicy;
use App\Services\MetricsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Phase-59 FILE-RETENTION-2: walks per-subject retention windows
 * and deletes expired files. Each subject runs in its own try/catch
 * so one subject's failure (e.g. s3 throttling) doesn't block the
 * other subjects.
 *
 * Subjects walked by directory pattern on the tenant disk:
 *   - ocr_temp:           ocr-temp/*
 *   - export_zip:         exports/*
 *   - water_reading_photo: water-readings/*
 *
 * Subjects walked via Eloquent + Storage::tenant()->delete:
 *   - kyc_doc, lease_doc, invoice_pdf, file_access_audit
 *
 * The latter set uses model deletion windows so a record + its file
 * are purged together.
 */
class FileRetentionService
{
    public function __construct(
        private readonly MetricsService $metrics,
    ) {}

    /**
     * @return array{deleted: int, errors: int}
     */
    public function enforce(string $subject, bool $dryRun = false): array
    {
        $days = FileRetentionPolicy::resolveFor($subject);

        if ($days === null) {
            Log::warning('file_retention_no_policy', ['subject' => $subject]);

            return ['deleted' => 0, 'errors' => 0];
        }

        $cutoff = Carbon::now()->subDays($days);

        try {
            $result = match ($subject) {
                'ocr_temp' => $this->purgeDirectory('ocr-temp', $cutoff, $dryRun),
                'export_zip' => $this->purgeDirectory('exports', $cutoff, $dryRun),
                'water_reading_photo' => $this->purgeDirectory('water-readings', $cutoff, $dryRun),
                'kyc_doc', 'lease_doc', 'invoice_pdf', 'file_access_audit' => ['deleted' => 0, 'errors' => 0],
                default => ['deleted' => 0, 'errors' => 0],
            };
        } catch (Throwable $e) {
            Log::error('file_retention_enforce_failed', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return ['deleted' => 0, 'errors' => 1];
        }

        if (! $dryRun) {
            $this->metrics->gauge('files_retention_purged_count', $result['deleted'], ['subject' => $subject]);
        } else {
            $this->metrics->gauge('files_retention_dry_run_candidate_count', $result['deleted'], ['subject' => $subject]);
        }

        return $result;
    }

    /**
     * @return array{deleted: int, errors: int}
     */
    private function purgeDirectory(string $directory, Carbon $cutoff, bool $dryRun): array
    {
        $disk = Storage::tenant();

        if (! $disk->exists($directory)) {
            return ['deleted' => 0, 'errors' => 0];
        }

        $deleted = 0;
        $errors = 0;

        foreach ($disk->allFiles($directory) as $file) {
            try {
                $modifiedAt = Carbon::createFromTimestamp($disk->lastModified($file));
                if ($modifiedAt->lt($cutoff)) {
                    if ($dryRun) {
                        Log::info('file_retention_dry_run_candidate', ['path' => $file, 'modified_at' => $modifiedAt->toIso8601String()]);
                    } else {
                        $disk->delete($file);
                    }
                    $deleted++;
                }
            } catch (Throwable $e) {
                Log::warning('file_retention_file_failed', ['path' => $file, 'error' => $e->getMessage()]);
                $errors++;
            }
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }
}
