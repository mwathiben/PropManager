<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessagePosted;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase-63 INBOX-NOTIFY-1: bridge unread inbox messages into the
 * NotificationService preference matrix. Fires email/SMS/WhatsApp/push
 * fallbacks ONLY when the recipient is plausibly offline — i.e. their
 * users.last_active_at is older than 5 minutes (or never set).
 *
 * Prevents in-app spam when the recipient is actively reading inbox
 * (the Reverb push already surfaces the message) while still catching
 * the case where they walk away mid-thread.
 *
 * Phase-16 RESIL backoff: $tries=4, $backoff=[30,60,300,1800] so a
 * transient gateway hiccup doesn't drop the notification on the floor.
 */
class SendUnreadMessageFallback implements ShouldQueue
{
    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 300, 1800];

    public const ACTIVE_WINDOW_MINUTES = 5;

    public const IDEMPOTENCY_TTL_SECONDS = 600;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handle(MessagePosted $event): void
    {
        $message = $event->message;

        // System messages (sender_id NULL) do not generate fallback
        // notifications — they are already audit lines, not human
        // communication.
        if ($message->sender_id === null) {
            return;
        }

        $thread = $message->thread()->withTrashed()->first();
        if ($thread === null) {
            return;
        }

        $preview = Str::limit($message->body, 120);

        $participantIds = DB::table('message_thread_participants')
            ->where('thread_id', $thread->id)
            ->where('user_id', '!=', $message->sender_id)
            ->pluck('user_id');

        foreach ($participantIds as $userId) {
            $this->notifyParticipant($userId, $message, $thread, $preview);
        }
    }

    /**
     * Attempt to send a fallback notification to a single participant.
     * Skips when idempotency key already exists, user not found, or user is online.
     *
     * @param  \App\Models\MessageThread  $thread
     * @param  \App\Models\Message  $message
     */
    private function notifyParticipant(int $userId, object $message, object $thread, string $preview): void
    {
        $idemKey = 'inbox:fallback:'.$message->id.':'.$userId;
        if (! Cache::add($idemKey, 1, self::IDEMPOTENCY_TTL_SECONDS)) {
            return;
        }

        $user = \App\Models\User::withoutGlobalScope('landlord')->find($userId);
        if ($user === null) {
            return;
        }

        // Skip when the user is plausibly online — Reverb already
        // delivered the in-app push.
        if ($this->isUserOnline($user)) {
            return;
        }

        $senderName = $message->sender?->name ?? __('inbox.notification.sender_unknown');

        $this->notifications->send(
            recipientId: $userId,
            type: Notification::TYPE_NEW_MESSAGE,
            subject: __('inbox.notification.subject', ['sender' => $senderName]),
            message: $preview,
            data: [
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'sender_name' => $senderName,
            ],
            landlordId: $thread->landlord_id,
        );
    }

    private function isUserOnline(\App\Models\User $user): bool
    {
        return $user->last_active_at !== null
            && $user->last_active_at->greaterThan(
                now()->subMinutes(self::ACTIVE_WINDOW_MINUTES),
            );
    }
}
