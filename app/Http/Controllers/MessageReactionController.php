<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\MessageReacted;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessageThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase-71 REACTIONS: toggles the caller's emoji reaction on a message.
 *
 * Shared by the landlord (message-threads) and tenant (tenant.inbox) route
 * groups — both gate on MessageThreadPolicy::view (participant pivot), so a
 * non-participant or a cross-thread/cross-tenant message id is rejected. The
 * emoji is constrained to the config allow-list, never arbitrary input.
 */
class MessageReactionController extends Controller
{
    public function toggle(Request $request, MessageThread $thread, Message $message): RedirectResponse
    {
        $this->authorize('view', $thread);
        abort_unless($message->thread_id === $thread->id, 404);

        $data = $request->validate([
            'emoji' => ['required', 'string', Rule::in(config('inbox.reactions'))],
        ]);

        $emoji = $data['emoji'];
        $userId = (int) $request->user()->id;

        $keys = ['message_id' => $message->id, 'user_id' => $userId, 'emoji' => $emoji];

        $existing = MessageReaction::query()->where($keys)->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            // createOrFirst is race-safe: a concurrent identical insert returns
            // the existing row instead of throwing on the unique index (no 500).
            MessageReaction::createOrFirst($keys);
        }

        $count = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('emoji', $emoji)
            ->count();

        broadcast(new MessageReacted($thread->id, $message->id, $emoji, $userId, $count))->toOthers();

        return back();
    }
}
