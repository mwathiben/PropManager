<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-71 REACTIONS: broadcasts a reaction toggle to the other thread
 * participants. Carries the authoritative post-toggle count for the emoji so
 * recipients can set (not guess) the pill count; chain ->toOthers() since the
 * actor already updated optimistically.
 */
class MessageReacted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $threadId,
        public int $messageId,
        public string $emoji,
        public int $userId,
        public int $count,
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
        return 'message.reacted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'emoji' => $this->emoji,
            'user_id' => $this->userId,
            'count' => $this->count,
        ];
    }
}
