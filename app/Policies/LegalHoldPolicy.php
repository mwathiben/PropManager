<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LegalHold;
use App\Models\User;
use App\Support\LegalHoldRegistry;

/**
 * Phase-64 LEGAL-HOLD-3: only the owning landlord may put a hold on
 * their own subject (or a super admin acting in cross-tenant ops
 * mode). Tenants + caretakers cannot freeze retention.
 *
 * Phase-65 MORPH-EXPAND-3: cross-tenant ownership gate generalised
 * across every ALLOWED_HOLDABLE_TYPES subject by delegating to each
 * subject model's existing landlord_id ownership check. Bypasses
 * TenantScope on lookup so cross-tenant attempts fail at the
 * landlord_id comparison instead of silently returning false on find.
 */
class LegalHoldPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function create(User $user, ?string $subjectType = null, ?int $subjectId = null): bool
    {
        if (! $user->isScopeOwner()) {
            return false;
        }

        if ($subjectType === null || $subjectId === null) {
            return true;
        }

        if (! in_array($subjectType, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES, true)) {
            return false;
        }

        return $this->ownsSubject($user, $subjectType, $subjectId);
    }

    public function release(User $user, LegalHold $hold): bool
    {
        if (! $user->isScopeOwner()) {
            return false;
        }

        if (! in_array($hold->holdable_type, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES, true)) {
            return false;
        }

        return $this->ownsSubject($user, $hold->holdable_type, (int) $hold->holdable_id);
    }

    public function auditExport(User $user): bool
    {
        return $user->isScopeOwner();
    }

    public function viewHistory(User $user, ?string $subjectType = null, ?int $subjectId = null): bool
    {
        if (! $user->isScopeOwner()) {
            return false;
        }

        if ($subjectType === null || $subjectId === null) {
            return false;
        }

        if (! in_array($subjectType, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES, true)) {
            return false;
        }

        return $this->ownsSubject($user, $subjectType, $subjectId);
    }

    private function ownsSubject(User $user, string $subjectClass, int $subjectId): bool
    {
        $query = $subjectClass::query();

        if (method_exists($query, 'withoutGlobalScopes')) {
            $query->withoutGlobalScopes();
        }

        $subject = $query->find($subjectId);

        if ($subject === null) {
            return false;
        }

        if (! isset($subject->landlord_id)) {
            return false;
        }

        return (int) $subject->landlord_id === (int) $user->id;
    }
}
