<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-63 INBOX-REALTIME-1: broadcasts a new message to every
 * participant on the parent thread via a PrivateChannel that is
 * authorised by the message_thread_participants pivot (NOT
 * landlord_id) so cross-tenant subscriptions cannot leak.
 *
 * Dispatchers should chain ->toOthers() (or broadcast(...)->toOthers())
 * to skip the sender, whose optimistic UI already shows the message.
 */
class MessagePosted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('inbox.thread.'.$this->message->thread_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing(['sender:id,name,role']);

        return [
            'message_id' => $this->message->id,
            'thread_id' => $this->message->thread_id,
            'sender' => $this->message->sender_id === null
                ? null
                : [
                    'id' => $this->message->sender?->id,
                    'name' => $this->message->sender?->name,
                    'role' => $this->message->sender?->role,
                ],
            'body' => $this->message->body,
            'message_type' => $this->message->message_type,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
