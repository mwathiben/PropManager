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

    /**
     * Phase-71 REACTIONS: collapse each message's loaded reactions relation
     * into a grouped {emoji,count,reacted} summary attribute (reacted is
     * relative to $userId), dropping the relation. Requires `reactions` loaded.
     */
    protected function attachReactionSummaries(MessageThread $thread, ?int $userId): void
    {
        $thread->messages->each(function ($message) use ($userId): void {
            $message->setAttribute('reactions', $message->reactionSummary($userId));
            $message->unsetRelation('reactions');
        });
    }

    /**
     * Phase-71 MEDIA-CI: replace each message's documents relation with a
     * compact attachment descriptor (is_image, size, scan_status + a
     * participant-gated signed-redirect URL on $routeName), so the bubble can
     * render thumbnails/chips without exposing storage paths. Requires
     * `documents` loaded.
     */
    protected function attachAttachmentMeta(MessageThread $thread, string $routeName): void
    {
        $thread->messages->each(function ($message) use ($thread, $routeName): void {
            $message->setAttribute('documents', $message->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'mime_type' => $doc->mime_type,
                'is_image' => $doc->isImage(),
                'file_size_formatted' => $doc->file_size_formatted,
                'scan_status' => $doc->scan_status,
                'url' => route($routeName, [$thread->id, $message->id, $doc->id]),
            ])->all());
            $message->unsetRelation('documents');
        });
    }
}
