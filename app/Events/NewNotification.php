<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.'.$this->notification->recipient_id),
        ];
    }

    public function broadcastWith(): array
    {
        $highPriorityTypes = [
            'arrears_notice',
            'eviction_notice',
            'caretaker_invitation',
            'tenant_invitation',
        ];

        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'subject' => $this->notification->subject,
            'message' => $this->notification->message,
            'priority' => in_array($this->notification->type, $highPriorityTypes) ? 'high' : 'normal',
            'created_at' => $this->notification->created_at->toISOString(),
            'time_ago' => $this->notification->created_at->diffForHumans(),
        ];
    }
}
