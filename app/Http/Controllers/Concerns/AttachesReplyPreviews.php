<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\MessageThread;

/**
 * Phase-71 REPLY-QUOTE: normalises each loaded message's quoted-message into a
 * compact `reply_to` attribute (id, sender_name, snippet) and drops the heavy
 * relation, so the show payload matches the MessagePosted broadcast shape and
 * never ships a full nested message graph. Requires `replyTo.sender` loaded.
 */
trait AttachesReplyPreviews
{
    protected function attachReplyPreviews(MessageThread $thread): void
    {
        $thread->messages->each(function ($message): void {
            $message->setAttribute('reply_to', $message->replyTo?->toReplyPreview());
            $message->unsetRelation('replyTo');
        });
    }
}
