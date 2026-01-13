<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterReading;
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

        Storage::disk('local')->makeDirectory($exportPath);

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
            'leases' => $this->getLeaseData($user),
            'invoices' => $this->getInvoiceData($user),
            'payments' => $this->getPaymentData($user),
            'documents' => $this->getDocumentData($user),
            'water_readings' => $this->getWaterReadingData($user),
            'activity_log' => $this->getActivityLog($user),
        ];

        // Write JSON export
        $jsonPath = "{$exportPath}/data_export.json";
        Storage::disk('local')->put($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Create ZIP archive
        $zipPath = storage_path("app/{$exportPath}/data_export_{$user->id}_{$exportId}.zip");
        $this->createZipArchive($exportPath, $zipPath, $user);

        // Clean up JSON file after zipping
        Storage::disk('local')->delete($jsonPath);

        return $zipPath;
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
            ->with(['unit.building.property'])
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
        })->get();

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
        })->get();

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
                // Note: Actual files are included in the ZIP archive
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
        $jsonContent = Storage::disk('local')->get("{$exportPath}/data_export.json");
        $zip->addFromString('data_export.json', $jsonContent);

        // Add user documents
        $documents = Document::where('documentable_type', User::class)
            ->where('documentable_id', $user->id)
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
        $leases = Lease::where('tenant_id', $user->id)->with('documents')->get();
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
        $exportDir = storage_path('app/exports');

        if (! is_dir($exportDir)) {
            return 0;
        }

        foreach (glob("{$exportDir}/*", GLOB_ONLYDIR) as $userDir) {
            foreach (glob("{$userDir}/*", GLOB_ONLYDIR) as $exportDir) {
                $dirTime = filemtime($exportDir);
                if ($dirTime < now()->subDays($daysToKeep)->timestamp) {
                    $this->deleteDirectory($exportDir);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Delete a directory recursively.
     */
    protected function deleteDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
