<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketActivityFactory extends Factory
{
    protected $model = TicketActivity::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'landlord_id' => fn (array $attrs) => $attrs['ticket_id'] instanceof Ticket ? $attrs['ticket_id']->landlord_id : Ticket::findOrFail($attrs['ticket_id'])->landlord_id,
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'action' => TicketActivity::ACTION_CREATED,
            'old_value' => null,
            'new_value' => null,
            'description' => 'Ticket created',
            'created_at' => now(),
        ];
    }

    public function created(): static
    {
        return $this->state([
            'action' => TicketActivity::ACTION_CREATED,
            'description' => 'Ticket created',
        ]);
    }

    public function statusChanged(string $oldStatus = 'open', string $newStatus = 'in_progress'): static
    {
        return $this->state([
            'action' => TicketActivity::ACTION_STATUS_CHANGED,
            'old_value' => $oldStatus,
            'new_value' => $newStatus,
            'description' => "Status changed from {$oldStatus} to {$newStatus}",
        ]);
    }

    public function assigned(): static
    {
        return $this->state([
            'action' => TicketActivity::ACTION_ASSIGNED,
            'description' => 'Ticket assigned',
        ]);
    }

    public function commented(): static
    {
        return $this->state([
            'action' => TicketActivity::ACTION_COMMENTED,
            'description' => 'Comment added',
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'action' => TicketActivity::ACTION_RESOLVED,
            'description' => 'Ticket resolved',
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'action' => TicketActivity::ACTION_CLOSED,
            'description' => 'Ticket closed',
        ]);
    }

    public function feedbackSubmitted(): static
    {
        return $this->state([
            'action' => TicketActivity::ACTION_FEEDBACK_SUBMITTED,
            'description' => 'Feedback submitted',
        ]);
    }

    public function systemAction(): static
    {
        return $this->state(['user_id' => null]);
    }

    public function forTicket(Ticket $ticket): static
    {
        return $this->state([
            'ticket_id' => $ticket->id,
            'landlord_id' => $ticket->landlord_id,
        ]);
    }

    public function byUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forLandlord(User $landlord, ?Ticket $ticket = null): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
            'ticket_id' => $ticket?->id ?? Ticket::factory()->forLandlord($landlord),
        ]);
    }
}
