<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;

/**
 * Phase-63 INBOX-COMPOSE-1 + INBOX-MOD-1: per-message authz. Reading
 * a message inherits from MessageThreadPolicy::view; deletion is gated
 * by the 5-minute sender window via Message::canBeDeletedBy.
 */
class MessagePolicy
{
    public function __construct(
        private readonly MessageThreadPolicy $threads = new MessageThreadPolicy,
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, Message $message): bool
    {
        $thread = $message->thread;

        if ($thread === null) {
            return false;
        }

        return $this->threads->view($user, $thread);
    }

    public function create(User $user, MessageThread $thread): bool
    {
        return $this->threads->reply($user, $thread);
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->canBeDeletedBy($user);
    }
}
