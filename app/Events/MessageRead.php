<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-67 READ-RECEIPTS-1: broadcasts that a participant advanced their
 * read cursor, so the other participants' clients can render live "seen"
 * status. Same pivot-authorised PrivateChannel as MessagePosted, so a
 * non-participant can never subscribe. Dispatch with ->toOthers() — the
 * reader's own UI already knows it read.
 *
 * Scalars only (no model) so the payload is explicit and the event
 * serialises cleanly from both the per-message and mark-all paths.
 */
class MessageRead implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $threadId,
        public int $userId,
        public string $userName,
        public string $readAtIso,
        public ?int $lastReadMessageId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('inbox.thread.'.$this->threadId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'user_id' => $this->userId,
            'name' => $this->userName,
            'read_at' => $this->readAtIso,
            'last_read_message_id' => $this->lastReadMessageId,
        ];
    }
}
