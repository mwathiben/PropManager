<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase-63 INBOX-MOD-1: sender-initiated soft-delete within 5 min.
 * Drops a system message ("Message deleted by sender") so the
 * recipient sees a placeholder for the missing message instead of
 * the message just disappearing.
 */
class MessageDeleteController extends Controller
{
    public function __invoke(Request $request, Message $message): RedirectResponse
    {
        $this->authorize('delete', $message);

        $thread = $message->thread;
        $message->delete();

        if ($thread !== null) {
            $thread->recordSystemEvent(__('inbox.message.deleted_by_sender'));
        }

        return back(303);
    }
}
