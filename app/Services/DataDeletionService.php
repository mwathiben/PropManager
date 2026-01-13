<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DeletionRequest;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataDeletionService
{
    /**
     * Create a deletion request (GDPR Article 17 - Right to Erasure).
     * This starts the grace period before actual deletion.
     */
    public function requestDeletion(User $user, ?string $reason = null): DeletionRequest
    {
        $graceDays = config('security.compliance.deletion_grace_days', 30);

        $request = DeletionRequest::create([
            'user_id' => $user->id,
            'reason' => $reason,
            'status' => 'pending',
            'requested_at' => now(),
            'scheduled_deletion_at' => now()->addDays($graceDays),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Log the deletion request
        AuditLog::create([
            'user_id' => $user->id,
            'landlord_id' => $user->isLandlord() ? $user->id : $user->landlord_id,
            'event_type' => 'deletion_requested',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'metadata' => [
                'deletion_request_id' => $request->id,
                'scheduled_deletion_at' => $request->scheduled_deletion_at->toIso8601String(),
                'compliance' => ['gdpr_article_17', 'kenya_dpa_section_28'],
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $request;
    }

    /**
     * Cancel a deletion request (user changed their mind).
     */
    public function cancelDeletion(DeletionRequest $request): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        $request->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $request->user_id,
            'event_type' => 'deletion_cancelled',
            'auditable_type' => User::class,
            'auditable_id' => $request->user_id,
            'metadata' => [
                'deletion_request_id' => $request->id,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return true;
    }

    /**
     * Execute deletion for all approved requests past their grace period.
     */
    public function processScheduledDeletions(): int
    {
        $processed = 0;

        $requests = DeletionRequest::where('status', 'pending')
            ->where('scheduled_deletion_at', '<=', now())
            ->get();

        foreach ($requests as $request) {
            try {
                $this->executeDeletion($request);
                $processed++;
            } catch (\Exception $e) {
                \Log::error("Failed to process deletion request {$request->id}", [
                    'error' => $e->getMessage(),
                ]);
                $request->update(['status' => 'failed']);
            }
        }

        return $processed;
    }

    /**
     * Execute the actual deletion/anonymization for a user.
     */
    public function executeDeletion(DeletionRequest $request): void
    {
        $user = $request->user;

        if (! $user) {
            $request->update(['status' => 'completed']);

            return;
        }

        DB::transaction(function () use ($user, $request) {
            // 1. Delete all user documents
            $this->deleteUserDocuments($user);

            // 2. Anonymize lease records (keep for financial/legal records)
            $this->anonymizeLeases($user);

            // 3. Anonymize invoice and payment records
            $this->anonymizeFinancialRecords($user);

            // 4. Delete/anonymize audit logs for this user
            $this->handleAuditLogs($user);

            // 5. Delete related records that can be fully removed
            $this->deleteRelatedRecords($user);

            // 6. Anonymize the user record itself
            $anonymizedEmail = $this->anonymizeUser($user);

            // 7. Update deletion request
            $request->update([
                'status' => 'completed',
                'completed_at' => now(),
                'anonymized_email' => $anonymizedEmail,
            ]);

            // 8. Final audit log (before user is anonymized)
            AuditLog::create([
                'user_id' => null, // User no longer exists in meaningful form
                'event_type' => 'user_data_deleted',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'metadata' => [
                    'deletion_request_id' => $request->id,
                    'original_email_hash' => hash('sha256', $anonymizedEmail),
                    'compliance' => ['gdpr_article_17', 'kenya_dpa_section_28'],
                ],
                'ip_address' => 'system',
                'user_agent' => 'Scheduled Deletion Job',
            ]);
        });
    }

    /**
     * Delete all documents uploaded by/for the user.
     */
    protected function deleteUserDocuments(User $user): void
    {
        // User's own documents
        $documents = Document::where('documentable_type', User::class)
            ->where('documentable_id', $user->id)
            ->get();

        foreach ($documents as $doc) {
            $doc->deleteFile();
            $doc->forceDelete();
        }

        // Documents uploaded by this user
        Document::where('uploaded_by', $user->id)->update([
            'uploaded_by' => null,
        ]);
    }

    /**
     * Anonymize lease records (keep structure for financial reporting).
     */
    protected function anonymizeLeases(User $user): void
    {
        Lease::where('tenant_id', $user->id)->update([
            'tenant_id' => null,
            // Keep financial data for accounting purposes
        ]);

        // Delete lease documents for this tenant
        $leaseIds = Lease::where('tenant_id', $user->id)->pluck('id');
        $leaseDocs = Document::where('documentable_type', Lease::class)
            ->whereIn('documentable_id', $leaseIds)
            ->get();

        foreach ($leaseDocs as $doc) {
            $doc->deleteFile();
            $doc->forceDelete();
        }
    }

    /**
     * Anonymize financial records while keeping aggregate data.
     */
    protected function anonymizeFinancialRecords(User $user): void
    {
        // Keep payment records but remove personal references
        Payment::whereHas('lease', function ($q) use ($user) {
            $q->where('tenant_id', $user->id);
        })->update([
            'notes' => null, // Remove any personal notes
        ]);
    }

    /**
     * Handle audit logs - anonymize but keep for compliance.
     */
    protected function handleAuditLogs(User $user): void
    {
        // Keep audit logs but anonymize user reference
        AuditLog::where('user_id', $user->id)->update([
            'user_id' => null,
            'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.anonymized', true)"),
        ]);

        // Remove IP addresses from old logs (data minimization)
        AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('created_at', '<', now()->subYears(1))
            ->update([
                'ip_address' => null,
                'user_agent' => null,
            ]);
    }

    /**
     * Delete records that can be fully removed.
     */
    protected function deleteRelatedRecords(User $user): void
    {
        // Delete tenant notes
        if (class_exists(\App\Models\TenantNote::class)) {
            \App\Models\TenantNote::where('tenant_id', $user->id)->delete();
        }

        // Delete emergency contacts
        if (class_exists(\App\Models\EmergencyContact::class)) {
            \App\Models\EmergencyContact::where('tenant_id', $user->id)->delete();
        }

        // Delete tenant activities
        if (class_exists(\App\Models\TenantActivity::class)) {
            \App\Models\TenantActivity::where('tenant_id', $user->id)->delete();
        }

        // Delete verifications
        if (class_exists(\App\Models\TenantVerification::class)) {
            \App\Models\TenantVerification::whereHas('lease', function ($q) use ($user) {
                $q->where('tenant_id', $user->id);
            })->delete();
        }
    }

    /**
     * Anonymize the user record.
     */
    protected function anonymizeUser(User $user): string
    {
        $originalEmail = $user->email;
        $anonymizedId = Str::uuid();

        $user->update([
            'name' => 'Deleted User',
            'email' => "deleted_{$anonymizedId}@anonymized.local",
            'password' => bcrypt(Str::random(64)), // Unguessable password
            'mobile_number' => null,
            'national_id' => null,
            'bank_details' => null,
            'remember_token' => null,
            'email_verified_at' => null,
        ]);

        // Invalidate all sessions
        DB::table('sessions')->where('user_id', $user->id)->delete();

        // Revoke all API tokens
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return $originalEmail;
    }

    /**
     * Get deletion status for a user.
     */
    public function getDeletionStatus(User $user): ?DeletionRequest
    {
        return DeletionRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();
    }

    /**
     * Check if user can request deletion.
     */
    public function canRequestDeletion(User $user): array
    {
        $blockers = [];

        // Landlords with active properties cannot be deleted
        if ($user->isLandlord()) {
            $activeLeases = Lease::where('landlord_id', $user->id)
                ->where('is_active', true)
                ->count();

            if ($activeLeases > 0) {
                $blockers[] = "You have {$activeLeases} active lease(s). Please terminate all leases first.";
            }

            $unpaidInvoices = Invoice::where('landlord_id', $user->id)
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->count();

            if ($unpaidInvoices > 0) {
                $blockers[] = "You have {$unpaidInvoices} unpaid invoice(s). Please resolve all payments first.";
            }
        }

        // Tenants with active leases
        if ($user->isTenant()) {
            $activeLease = Lease::where('tenant_id', $user->id)
                ->where('is_active', true)
                ->exists();

            if ($activeLease) {
                $blockers[] = 'You have an active lease. Please complete the move-out process first.';
            }

            $unpaidInvoices = Invoice::whereHas('lease', function ($q) use ($user) {
                $q->where('tenant_id', $user->id);
            })->whereIn('status', ['sent', 'partial', 'overdue'])->count();

            if ($unpaidInvoices > 0) {
                $blockers[] = "You have {$unpaidInvoices} unpaid invoice(s). Please settle all payments first.";
            }
        }

        // Check for pending deletion request
        $pendingRequest = $this->getDeletionStatus($user);
        if ($pendingRequest) {
            $blockers[] = "A deletion request is already pending (scheduled for {$pendingRequest->scheduled_deletion_at->format('F j, Y')}).";
        }

        return [
            'can_delete' => empty($blockers),
            'blockers' => $blockers,
        ];
    }
}
