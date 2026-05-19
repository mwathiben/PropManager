<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterReading;
// Phase-21 DEFER-DPA-2: large-export detection seam (Phase-13 BREACH-2 closure).
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class DataExportService
{
    /**
     * Export all personal data for a user (GDPR Article 20 - Right to Data Portability).
     */
    public function exportUserData(User $user): string
    {
        $exportId = Str::uuid();
        $exportPath = "exports/{$user->id}/{$exportId}";

        Storage::tenant()->makeDirectory($exportPath);

        $leases = $this->getLeaseData($user);
        $invoices = $this->getInvoiceData($user);
        $payments = $this->getPaymentData($user);
        $documents = $this->getDocumentData($user);
        $waterReadings = $this->getWaterReadingData($user);
        $activityLog = $this->getActivityLog($user);

        $data = [
            'export_info' => [
                'exported_at' => now()->toIso8601String(),
                'export_id' => $exportId,
                'data_subject' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
                'compliance' => [
                    'gdpr_article' => '20 - Right to Data Portability',
                    'kenya_dpa_section' => '26 - Right of Access',
                ],
            ],
            'personal_information' => $this->getPersonalInfo($user),
            'leases' => $leases,
            'invoices' => $invoices,
            'payments' => $payments,
            'documents' => $documents,
            'water_readings' => $waterReadings,
            'activity_log' => $activityLog,
            // Phase-65 RETENTION-INTEGRATION-2: DPA Article 17 right-
            // to-erasure carves out legal_obligation processing under
            // Article 17(3)(b). Any active LegalHold on this user's
            // data WILL survive an erasure request — expose the list
            // so the requester (and operator handling) sees which
            // records remain and why.
            'legal_holds_blocking_erasure' => $this->getLegalHoldsBlockingErasure($user),
        ];

        // Phase-21 DEFER-DPA-2 (closes Phase-13 BREACH-2 deferral):
        // wire the IncidentDetector::checkLargeDataExport call site. Pre-
        // Phase-21 the detector rule existed but no consumer called it,
        // so a malicious actor (or compromised account) running large
        // exports never tripped the SuspiciousActivityDetected event.
        // Threshold default 10000 rows; debounced 60min per Phase-13
        // BREACH-1 to avoid incident-flood on legitimate bulk operations.
        $rowCount = count($leases) + count($invoices) + count($payments)
            + count($documents) + count($waterReadings) + count($activityLog);
        try {
            app(IncidentDetector::class)->checkLargeDataExport(
                $user->id,
                $rowCount,
                'gdpr_portability',
            );
        } catch (\Throwable $e) {
            // Detector failures must NOT block the user's right-to-export
            // (Kenya DPA Section 26 / GDPR Article 20 are statutory). Log
            // for ops and continue.
            \Illuminate\Support\Facades\Log::channel(config('logging.schedule_channel', 'stack'))
                ->error('DataExportService: IncidentDetector::checkLargeDataExport failed', [
                    'user_id' => $user->id,
                    'row_count' => $rowCount,
                    'exception' => $e->getMessage(),
                ]);
        }

        // Write JSON export
        $jsonPath = "{$exportPath}/data_export.json";
        Storage::tenant()->put($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Phase-59 PATH-CAVEAT-2: ZipArchive needs a real filesystem
        // path (Storage::tenant()->path() throws on s3). TempFileResolver
        // builds the archive at a local-driver path on local, or at a
        // temp dir on s3; after the ZIP is built we upload it back to
        // the tenant disk under the canonical relative path.
        $relativeZipPath = "{$exportPath}/data_export_{$user->id}_{$exportId}.zip";
        $handle = app(\App\Services\Storage\TempFileResolver::class)->for($relativeZipPath);

        try {
            $this->createZipArchive($exportPath, $handle->path(), $user);

            // Phase-59 PATH-CAVEAT-2: when the handle owns its temp
            // file (s3 path) the ZIP isn't yet on the tenant disk —
            // upload it. On local the handle wraps the canonical path
            // so this put() is a no-op overwrite.
            if (Storage::tenant()->exists($relativeZipPath) === false) {
                Storage::tenant()->put($relativeZipPath, file_get_contents($handle->path()));
            }
        } finally {
            $handle->cleanup();
        }

        // Clean up JSON file after zipping
        Storage::tenant()->delete($jsonPath);

        // Phase-59 PATH-CAVEAT-2: return the tenant-disk-relative path.
        // Callers (ExportUserData job + GdprController::immediateExport)
        // were previously expecting absolute paths under storage/app/;
        // they were updated to read via the tenant disk abstraction.
        return $relativeZipPath;
    }

    /**
     * Phase-65 RETENTION-INTEGRATION-2: list every active LegalHold
     * keyed to this user's data across all ALLOWED_HOLDABLE_TYPES.
     * Empty array when user has no holds. Article 17(3)(b) lawful-
     * basis exemption documented in compliance metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getLegalHoldsBlockingErasure(User $user): array
    {
        $holds = [];

        $leaseIds = $user->leases()->withoutGlobalScopes()->pluck('id')->all();

        $invoiceIds = $leaseIds === []
            ? []
            : \App\Models\Invoice::query()->withoutGlobalScopes()
                ->whereIn('lease_id', $leaseIds)->pluck('id')->all();

        $ticketIds = \App\Models\Ticket::query()->withoutGlobalScopes()
            ->where('reporter_id', $user->id)->pluck('id')->all();

        $documentIds = \App\Models\Document::query()->withoutGlobalScopes()
            ->where(function ($q) use ($user, $leaseIds) {
                $q->where(function ($qq) use ($user) {
                    $qq->where('documentable_type', User::class)
                        ->where('documentable_id', $user->id);
                });
                if ($leaseIds !== []) {
                    $q->orWhere(function ($qq) use ($leaseIds) {
                        $qq->where('documentable_type', \App\Models\Lease::class)
                            ->whereIn('documentable_id', $leaseIds);
                    });
                }
            })->pluck('id')->all();

        $threadIds = \App\Models\MessageThread::query()->withoutGlobalScopes()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id')->all();

        $subjectMap = [
            \App\Models\Invoice::class => $invoiceIds,
            \App\Models\Ticket::class => $ticketIds,
            \App\Models\Document::class => $documentIds,
            \App\Models\MessageThread::class => $threadIds,
        ];

        foreach ($subjectMap as $subjectClass => $ids) {
            if ($ids === []) {
                continue;
            }

            $rows = \App\Models\LegalHold::query()
                ->where('holdable_type', $subjectClass)
                ->whereIn('holdable_id', $ids)
                ->whereNull('released_at')
                ->get();

            foreach ($rows as $row) {
                $holds[] = [
                    'subject_type' => class_basename($subjectClass),
                    'subject_id' => (int) $row->holdable_id,
                    'held_at' => $row->held_at?->toIso8601String(),
                    'reason' => $row->reason,
                    'lawful_basis' => 'legal_obligation',
                    'erasure_carve_out' => 'GDPR Article 17(3)(b) / Kenya DPA Section 30',
                ];
            }
        }

        return $holds;
    }

    /**
     * Get personal information for the user.
     */
    protected function getPersonalInfo(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile_number' => $user->mobile_number,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'account_created_at' => $user->created_at->toIso8601String(),
            'last_updated_at' => $user->updated_at->toIso8601String(),
            // Note: Sensitive fields like national_id and bank_details are encrypted
            // and only included if the user is the data subject themselves
            'national_id' => $user->national_id ? '[ENCRYPTED - Available upon request]' : null,
            'bank_details' => $user->bank_details ? '[ENCRYPTED - Available upon request]' : null,
        ];
    }

    /**
     * Get lease data for the user.
     */
    protected function getLeaseData(User $user): array
    {
        $leases = Lease::where('tenant_id', $user->id)
            ->select(['id', 'unit_id', 'start_date', 'end_date', 'rent_amount', 'deposit_amount', 'service_charge', 'is_active', 'created_at'])
            ->with([
                'unit:id,unit_number,building_id',
                'unit.building:id,name,property_id',
                'unit.building.property:id,name',
            ])
            ->get();

        return $leases->map(function ($lease) {
            return [
                'id' => $lease->id,
                'property' => $lease->unit?->building?->property?->name,
                'building' => $lease->unit?->building?->name,
                'unit' => $lease->unit?->unit_number,
                'start_date' => $lease->start_date?->toDateString(),
                'end_date' => $lease->end_date?->toDateString(),
                'rent_amount' => $lease->rent_amount,
                'deposit_amount' => $lease->deposit_amount,
                'service_charge' => $lease->service_charge,
                'is_active' => $lease->is_active,
                'created_at' => $lease->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Get invoice data for the user.
     */
    protected function getInvoiceData(User $user): array
    {
        $invoices = Invoice::whereHas('lease', function ($q) use ($user) {
            $q->where('tenant_id', $user->id);
        })
            ->select(['id', 'invoice_number', 'lease_id', 'due_date', 'billing_period_start', 'rent_due', 'water_due', 'arrears', 'total_due', 'amount_paid', 'status', 'created_at'])
            ->get();

        return $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'due_date' => $invoice->due_date?->toDateString(),
                'billing_period_start' => $invoice->billing_period_start?->toDateString(),
                'rent_due' => $invoice->rent_due,
                'water_due' => $invoice->water_due,
                'arrears' => $invoice->arrears,
                'total_due' => $invoice->total_due,
                'amount_paid' => $invoice->amount_paid,
                'status' => $invoice->status,
                'created_at' => $invoice->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Get payment data for the user.
     */
    protected function getPaymentData(User $user): array
    {
        $payments = Payment::whereHas('lease', function ($q) use ($user) {
            $q->where('tenant_id', $user->id);
        })
            ->select(['id', 'amount', 'payment_method', 'payment_date', 'reference', 'notes', 'created_at'])
            ->get();

        return $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_date' => $payment->payment_date?->toDateString(),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Get document metadata for the user.
     */
    protected function getDocumentData(User $user): array
    {
        $documents = Document::where('documentable_type', User::class)
            ->where('documentable_id', $user->id)
            ->select(['id', 'title', 'document_type', 'file_name', 'file_size', 'mime_type', 'created_at'])
            ->get();

        return $documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'document_type' => $doc->document_type,
                'file_name' => $doc->file_name,
                'file_size' => $doc->file_size_formatted,
                'mime_type' => $doc->mime_type,
                'uploaded_at' => $doc->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Get water reading data for the user's units.
     */
    protected function getWaterReadingData(User $user): array
    {
        // Get readings for units the user has/had leases on
        $unitIds = Lease::where('tenant_id', $user->id)->pluck('unit_id');

        $readings = WaterReading::whereIn('unit_id', $unitIds)
            ->whereHas('unit.leases', function ($q) use ($user) {
                $q->where('tenant_id', $user->id);
            })
            ->select(['id', 'reading_date', 'previous_reading', 'current_reading', 'consumption', 'cost', 'status'])
            ->get();

        return $readings->map(function ($reading) {
            return [
                'id' => $reading->id,
                'reading_date' => $reading->reading_date?->toDateString(),
                'previous_reading' => $reading->previous_reading,
                'current_reading' => $reading->current_reading,
                'consumption' => $reading->consumption,
                'cost' => $reading->cost,
                'status' => $reading->status,
            ];
        })->toArray();
    }

    /**
     * Get audit/activity log for the user.
     */
    protected function getActivityLog(User $user): array
    {
        $logs = AuditLog::where('user_id', $user->id)
            ->orWhere(function ($q) use ($user) {
                $q->where('auditable_type', User::class)
                    ->where('auditable_id', $user->id);
            })
            ->select(['id', 'event_type', 'description', 'auditable_type', 'auditable_id', 'ip_address', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get();

        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'description' => $log->description,
                'model_type' => class_basename($log->auditable_type),
                'model_id' => $log->auditable_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Create a ZIP archive of all exported data including documents.
     */
    protected function createZipArchive(string $exportPath, string $zipPath, User $user): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create ZIP archive');
        }

        // Add JSON export
        $jsonContent = Storage::tenant()->get("{$exportPath}/data_export.json");
        $zip->addFromString('data_export.json', $jsonContent);

        // Add user documents
        $documents = Document::where('documentable_type', User::class)
            ->where('documentable_id', $user->id)
            ->select(['id', 'document_type', 'file_name', 'file_path'])
            ->get();

        foreach ($documents as $doc) {
            if ($doc->fileExists()) {
                $zip->addFile(
                    $doc->getFullPath(),
                    "documents/{$doc->document_type}/{$doc->file_name}"
                );
            }
        }

        // Add lease documents
        $leases = Lease::where('tenant_id', $user->id)
            ->select(['id'])
            ->with(['documents' => function ($q) {
                $q->select(['id', 'documentable_id', 'documentable_type', 'file_name', 'file_path']);
            }])
            ->get();
        foreach ($leases as $lease) {
            foreach ($lease->documents as $doc) {
                if ($doc->fileExists()) {
                    $zip->addFile(
                        $doc->getFullPath(),
                        "lease_documents/{$lease->id}/{$doc->file_name}"
                    );
                }
            }
        }

        // Add README
        $readme = $this->generateReadme($user);
        $zip->addFromString('README.txt', $readme);

        $zip->close();
    }

    /**
     * Generate README file explaining the export.
     */
    protected function generateReadme(User $user): string
    {
        return <<<README
DATA EXPORT - PropManager
==========================

Export Date: {$this->now()}
User: {$user->name} ({$user->email})

This archive contains your personal data as stored in PropManager,
in compliance with:
- GDPR Article 20 (Right to Data Portability)
- Kenya Data Protection Act 2019, Section 26 (Right of Access)

CONTENTS:
---------
1. data_export.json - All your personal data in machine-readable format
2. documents/ - Your uploaded identity documents
3. lease_documents/ - Documents related to your lease agreements

DATA CATEGORIES:
----------------
- Personal Information (name, email, phone)
- Lease History
- Invoice Records
- Payment History
- Water Usage Records
- Activity/Audit Log

For questions about your data, contact your landlord or PropManager support.

SECURITY NOTE:
--------------
This export contains sensitive personal information. Please store it
securely and delete it when no longer needed.

README;
    }

    /**
     * Get current timestamp formatted.
     */
    protected function now(): string
    {
        return now()->format('Y-m-d H:i:s T');
    }

    /**
     * Clean up old exports (retention policy).
     */
    public function cleanupOldExports(int $daysToKeep = 7): int
    {
        $deleted = 0;
        $disk = Storage::tenant();

        if (! $disk->exists('exports')) {
            return 0;
        }

        $cutoffDate = now()->subDays($daysToKeep);

        foreach ($disk->directories('exports') as $userDir) {
            foreach ($disk->directories($userDir) as $exportDir) {
                $dirTime = $disk->lastModified($exportDir);
                if ($dirTime < $cutoffDate->timestamp) {
                    $disk->deleteDirectory($exportDir);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
