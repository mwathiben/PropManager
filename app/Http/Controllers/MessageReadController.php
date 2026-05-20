<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\MessageRead;
use App\Models\Message;
use App\Models\MessageThreadParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-63 INBOX-REALTIME-2: tick a message as read for the current
 * user. Idempotent — re-marking the same message is a no-op when the
 * stored last_read_at is already >= the message timestamp.
 */
class MessageReadController extends Controller
{
    public function __invoke(Request $request, Message $message): RedirectResponse
    {
        $this->authorize('view', $message);

        $user = $request->user();
        $messageAt = $message->created_at;

        $participant = MessageThreadParticipant::query()
            ->where('thread_id', $message->thread_id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant === null) {
            abort(403);
        }

        if (
            $participant->last_read_at === null
            || ($messageAt !== null && $participant->last_read_at->lessThan($messageAt))
        ) {
            $readAt = $messageAt ?? now();
            $participant->update(['last_read_at' => $readAt]);

            // Phase-67 READ-RECEIPTS-1: tell the other participants the
            // cursor moved (live "seen"), and refresh the reader's own
            // cached unread total. Only on a genuine advance.
            broadcast(new MessageRead(
                $message->thread_id,
                $user->id,
                (string) $user->name,
                $readAt->toISOString(),
                $message->id,
            ))->toOthers();

            Cache::forget('inbox:unread:'.$user->id);
        }

        return back(303);
    }
}
