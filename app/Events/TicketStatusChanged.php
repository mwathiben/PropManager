<?php

namespace App\Events;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public TicketStatus $oldStatus,
        public TicketStatus $newStatus
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('landlord.'.$this->ticket->landlord_id),
            new PrivateChannel('tenant.'.$this->ticket->reporter_id),
        ];
    }

    public function broadcastWith(): array
    {
        $landlordOpenCount = Ticket::where('landlord_id', $this->ticket->landlord_id)
            ->open()->count();

        $caretakerOpenCount = $this->ticket->assigned_to
            ? Ticket::where('assigned_to', $this->ticket->assigned_to)->open()->count()
            : null;

        return [
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'priority' => $this->ticket->priority,
            'category' => $this->ticket->category,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
            'updated_at' => $this->ticket->updated_at->toISOString(),
            'assigned_to' => $this->ticket->assigned_to,
            'reporter_id' => $this->ticket->reporter_id,
            'building_id' => $this->ticket->building_id,
            'unit_id' => $this->ticket->unit_id,
            'landlord_open_count' => $landlordOpenCount,
            'caretaker_open_count' => $caretakerOpenCount,
        ];
    }
}
