<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageThreadParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            $participant->update([
                'last_read_at' => $messageAt ?? now(),
            ]);
        }

        return back(303);
    }
}
