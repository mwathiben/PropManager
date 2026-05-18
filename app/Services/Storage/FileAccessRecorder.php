<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\FileAccessAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase-59 ACCESS-AUDIT-2: persists FileAccessAudit rows for every
 * PII-bearing file download. Fail-soft via Log::warning — auditing
 * must NEVER 500 a legitimate download.
 *
 * Wired into DocumentController, LeaseController,
 * TenantDocumentsController, TenantKycController, WaterReadingController
 * after ownership validation passes.
 */
class FileAccessRecorder
{
    public function record(
        User $user,
        Model $subject,
        string $action,
        ?Request $request = null,
        ?string $accessedPath = null,
    ): void {
        try {
            FileAccessAudit::create([
                'user_id' => $user->id,
                'landlord_id' => $this->resolveLandlordId($user, $subject),
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'action' => $action,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'accessed_path' => $accessedPath,
                'accessed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('file_access_audit_persist_failed', [
                'user_id' => $user->id,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveLandlordId(User $user, Model $subject): int
    {
        $subjectLandlordId = $subject->getAttribute('landlord_id');
        if (is_int($subjectLandlordId)) {
            return $subjectLandlordId;
        }

        return $user->landlord_id ?? $user->id;
    }
}
