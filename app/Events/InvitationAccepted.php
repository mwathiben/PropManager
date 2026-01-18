<?php

namespace App\Events;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('landlord.'.$this->invitation->landlord_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->invitation->load('property');

        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'property_name' => $this->invitation->property->name,
            'accepted_by' => $this->user->name,
            'role' => $this->user->role,
            'accepted_at' => now()->format('M d, Y'),
        ];
    }
}
