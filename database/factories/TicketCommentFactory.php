<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'landlord_id' => fn (array $attrs) => $attrs['ticket_id'] instanceof Ticket ? $attrs['ticket_id']->landlord_id : Ticket::factory()->make()->landlord_id,
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'comment' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(['is_internal' => true]);
    }

    public function public(): static
    {
        return $this->state(['is_internal' => false]);
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
