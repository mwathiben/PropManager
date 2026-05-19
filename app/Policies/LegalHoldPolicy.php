<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LegalHold;
use App\Models\MessageThread;
use App\Models\User;

/**
 * Phase-64 LEGAL-HOLD-3: only the owning landlord may put a hold on
 * their own thread (or a super admin acting in cross-tenant ops
 * mode). Tenants + caretakers cannot freeze retention.
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
        return $user->isLandlord();
    }

    public function create(User $user, ?string $subjectType = null, ?int $subjectId = null): bool
    {
        if (! $user->isLandlord()) {
            return false;
        }

        if ($subjectType === MessageThread::class && $subjectId !== null) {
            $thread = MessageThread::query()
                ->withoutGlobalScope('landlord')
                ->find($subjectId);

            if ($thread === null) {
                return false;
            }

            return (int) $thread->landlord_id === (int) $user->id;
        }

        return true;
    }

    public function release(User $user, LegalHold $hold): bool
    {
        if (! $user->isLandlord()) {
            return false;
        }

        $subjectClass = $hold->holdable_type;
        if ($subjectClass !== MessageThread::class) {
            return false;
        }

        $thread = MessageThread::query()
            ->withoutGlobalScope('landlord')
            ->find($hold->holdable_id);

        if ($thread === null) {
            return false;
        }

        return (int) $thread->landlord_id === (int) $user->id;
    }
}
