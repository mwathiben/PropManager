<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MessageThread;
use App\Models\User;

/**
 * Phase-63 INBOX-COMPOSE-1: gate landlord + tenant access to message
 * threads. Participant pivot is the source of truth for `view`; only
 * landlords may archive or lock; tenants may create top-level threads
 * to their own landlord.
 */
class MessageThreadPolicy
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
        return $user->isScopeOwner() || $user->isCaretaker() || $user->isTenant();
    }

    public function view(User $user, MessageThread $thread): bool
    {
        return $thread->participants()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner() || $user->isCaretaker() || $user->isTenant();
    }

    public function reply(User $user, MessageThread $thread): bool
    {
        if (! $thread->isOpen()) {
            return false;
        }

        return $thread->participants()->whereKey($user->id)->exists();
    }

    public function archive(User $user, MessageThread $thread): bool
    {
        if (! $user->isScopeOwner()) {
            return false;
        }

        return $thread->landlord_id === $user->id;
    }

    public function lock(User $user, MessageThread $thread): bool
    {
        return $this->archive($user, $thread);
    }
}
