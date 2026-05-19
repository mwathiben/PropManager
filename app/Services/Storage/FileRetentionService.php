<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Document;
use App\Models\FileRetentionPolicy;
use App\Services\MetricsService;
use App\Support\LegalHoldRegistry;
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
                'kyc_doc' => $this->purgeDocumentsByType('tenant_id', $cutoff, $dryRun),
                'lease_doc' => $this->purgeDocumentsByType('lease_agreement', $cutoff, $dryRun),
                'invoice_pdf' => $this->purgeDocumentsByType('other', $cutoff, $dryRun),
                'file_access_audit' => ['deleted' => 0, 'errors' => 0],
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

        // Phase-65 RETENTION-INTEGRATION-1: per-enforce held-count
        // for Document-backed subjects. Hoisted out of the chunk loop
        // so a single subject doesn't emit the gauge thrice.
        if (in_array($subject, ['kyc_doc', 'lease_doc', 'invoice_pdf'], true)) {
            $this->metrics->gauge(
                'files_retention_held_count',
                count(LegalHoldRegistry::heldIdsFor(Document::class)),
                ['subject' => $subject],
            );
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

    /**
     * Phase-65 RETENTION-INTEGRATION-1: model-bound retention for
     * Document rows. Honors LegalHoldRegistry::heldIdsFor exclusions.
     *
     * Per-document disk delete is landlord-scoped via Storage::tenant
     * ($document->landlord_id) so the per-landlord PrefixedDisk
     * (Phase-59 TENANT-ROUTING) resolves the correct prefix.
     *
     * Soft-delete asymmetry is intentional: row stays as audit tail,
     * file content is erased — Kenya DPA Section 30 retention contract.
     *
     * @return array{deleted: int, errors: int}
     */
    private function purgeDocumentsByType(string $documentType, Carbon $cutoff, bool $dryRun): array
    {
        $heldIds = LegalHoldRegistry::heldIdsFor(Document::class);

        $candidates = Document::query()
            ->where('document_type', $documentType)
            ->where('created_at', '<', $cutoff)
            ->when($heldIds !== [], fn ($q) => $q->whereNotIn('id', $heldIds))
            ->get();

        $deleted = 0;
        $errors = 0;
        $orphans = 0;

        foreach ($candidates as $document) {
            try {
                if (! $dryRun) {
                    try {
                        Storage::tenant((int) $document->landlord_id)->delete($document->file_path);
                    } catch (Throwable $e) {
                        $orphans++;
                        Log::warning('file_retention_disk_delete_failed', [
                            'path' => $document->file_path,
                            'landlord_id' => $document->landlord_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $document->delete();
                }
                $deleted++;
            } catch (Throwable $e) {
                Log::warning('file_retention_document_failed', [
                    'id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        if (! $dryRun && $orphans > 0) {
            $this->metrics->gauge('files_retention_orphan_count', $orphans, ['document_type' => $documentType]);
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }
}
