<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MessageThread;
use Illuminate\Http\RedirectResponse;

/**
 * Phase-63 INBOX-MOD-1: landlord-only archive / lock / unlock state
 * transitions on MessageThread.status. Every transition records a
 * system message so participants see the timeline.
 */
class MessageThreadModerationController extends Controller
{
    public function archive(MessageThread $thread): RedirectResponse
    {
        $this->authorize('archive', $thread);

        $thread->update(['status' => MessageThread::STATUS_ARCHIVED]);
        $thread->recordSystemEvent(__('inbox.thread_archived'));

        return back(303)->with('status', __('inbox.thread_archived'));
    }

    public function lock(MessageThread $thread): RedirectResponse
    {
        $this->authorize('lock', $thread);

        $thread->update(['status' => MessageThread::STATUS_LOCKED]);
        $thread->recordSystemEvent(__('inbox.message.thread_locked_by_landlord'));

        return back(303)->with('status', __('inbox.thread_locked'));
    }

    public function unlock(MessageThread $thread): RedirectResponse
    {
        $this->authorize('lock', $thread);

        $thread->update(['status' => MessageThread::STATUS_OPEN]);
        $thread->recordSystemEvent(__('inbox.message.thread_unlocked_by_landlord'));

        return back(303);
    }
}
