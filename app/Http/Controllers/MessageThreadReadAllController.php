<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\MessageRead;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-67 READ-RECEIPTS-1: mark an entire thread read for the caller —
 * advances their participant cursor to the thread's latest message,
 * broadcasts MessageRead, and busts their unread cache. Role-agnostic:
 * gated by the participant pivot via MessageThreadPolicy::view, so the
 * same action serves landlords, caretakers, and tenants.
 */
class MessageThreadReadAllController extends Controller
{
    public function __invoke(Request $request, MessageThread $thread): RedirectResponse
    {
        $this->authorize('view', $thread);

        $user = $request->user();

        $participant = MessageThreadParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant === null) {
            abort(403);
        }

        $lastMessage = $thread->messages()->latest('created_at')->first();
        $readAt = $lastMessage?->created_at ?? now();

        if ($participant->last_read_at === null || $participant->last_read_at->lessThan($readAt)) {
            $participant->update(['last_read_at' => $readAt]);

            broadcast(new MessageRead(
                $thread->id,
                $user->id,
                (string) $user->name,
                $readAt->toISOString(),
                $lastMessage?->id,
            ))->toOthers();

            Cache::forget('inbox:unread:'.$user->id);
        }

        return back(303);
    }
}
